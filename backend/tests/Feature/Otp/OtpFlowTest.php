<?php

namespace Tests\Feature\Otp;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\Visitor;
use App\Models\Vote;
use App\Services\Sms\SmsSender;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * Visitor registration and per-event OTP verification (F5, F6).
 */
class OtpFlowTest extends TestCase
{
    use RefreshDatabase;

    private const VENUE_IP = '203.0.113.10';

    private const PHONE = '+962791234567';

    private Event $event;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
        $this->event = $this->openEvent('mc2026');
    }

    public function test_a_visitor_requests_a_code_by_sms(): void
    {
        $this->requestCode()
            ->assertAccepted()
            ->assertExactJson(['data' => ['expires_in' => 300, 'resend_after' => 60]]);

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame(self::PHONE, $this->sms->sent[0]['to']);
        $this->assertMatchesRegularExpression('/^Your Maker Collective 2026 voting code is \d{6}\. It expires in 5 minutes\.$/', $this->sms->sent[0]['message']);
    }

    public function test_the_phone_is_stored_encrypted_and_found_by_its_blind_index(): void
    {
        $this->requestCode(phone: '0791234567');

        $row = DB::table('visitors')->sole();
        $this->assertStringNotContainsString('791234567', $row->phone_encrypted);
        $this->assertSame(PhoneNumber::hash(self::PHONE), $row->phone_hash);
        $this->assertSame(self::PHONE, Visitor::sole()->phone_encrypted);
    }

    public function test_the_same_phone_in_another_format_is_the_same_visitor(): void
    {
        $this->requestCode(phone: '0791234567');
        $this->travel(61)->seconds();
        $this->requestCode(phone: '+962 79 123 4567')->assertAccepted();

        $this->assertSame(1, Visitor::count());
        $this->assertSame(1, EventRegistration::count());
    }

    public function test_the_correct_code_verifies_the_phone_and_returns_a_token_for_this_event(): void
    {
        $this->requestCode(name: 'Lina Haddad');

        $response = $this->verify($this->sms->lastCodeFor(self::PHONE))
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.visitor.full_name', 'Lina Haddad');

        $this->assertNotNull(EventRegistration::sole()->phone_verified_at);
        $this->getJson('/api/v1/events/mc2026/me', ['Authorization' => 'Bearer '.$response->json('data.token')])
            ->assertOk()
            ->assertJsonPath('data.visitor.full_name', 'Lina Haddad');
    }

    public function test_the_token_expires_with_the_event_window(): void
    {
        $this->event->update(['closes_at' => now()->addHours(2)]);
        $this->requestCode();

        $expiresAt = $this->verify($this->sms->lastCodeFor(self::PHONE))->json('data.expires_at');

        $this->assertSame($this->event->fresh()->closes_at->toIso8601ZuluString(), $expiresAt);
    }

    public function test_a_wrong_code_reports_the_attempts_left_and_the_code_dies_at_the_limit(): void
    {
        $this->event->update(['otp_max_attempts' => 3]);
        $this->requestCode();
        $code = $this->sms->lastCodeFor(self::PHONE);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->verify($wrong)->assertUnprocessable()->assertJsonPath('error.code', 'OTP_INVALID')->assertJsonPath('error.details.attempts_remaining', 2);
        $this->verify($wrong)->assertJsonPath('error.details.attempts_remaining', 1);
        $this->verify($wrong)->assertJsonPath('error.code', 'OTP_EXPIRED');

        // Even the right code no longer works.
        $this->verify($code)->assertJsonPath('error.code', 'OTP_EXPIRED');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $this->requestCode();
        $this->travel(301)->seconds();

        $this->verify($this->sms->lastCodeFor(self::PHONE))->assertJsonPath('error.code', 'OTP_EXPIRED');
    }

    public function test_a_code_works_only_once_and_a_new_request_replaces_the_old_code(): void
    {
        $this->requestCode();
        $first = $this->sms->lastCodeFor(self::PHONE);
        $this->travel(61)->seconds();
        $this->requestCode();
        $second = $this->sms->lastCodeFor(self::PHONE);

        // The old code is now simply a wrong code for the active one.
        if ($first !== $second) {
            $this->verify($first)->assertJsonPath('error.code', 'OTP_INVALID');
        }
        $this->verify($second)->assertOk();
        $this->verify($second)->assertJsonPath('error.code', 'OTP_EXPIRED');
    }

    public function test_codes_cannot_be_resent_during_the_cooldown(): void
    {
        $this->requestCode();

        $this->requestCode()
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'OTP_RATE_LIMITED')
            ->assertHeader('Retry-After');
        $this->assertCount(1, $this->sms->sent);
    }

    public function test_a_phone_gets_at_most_five_codes_per_fifteen_minutes_across_events(): void
    {
        $events = [$this->event, ...array_map(fn (int $i) => $this->openEvent("event-{$i}"), range(2, 6))];

        foreach (array_slice($events, 0, 5) as $event) {
            $this->requestCode(event: $event)->assertAccepted();
        }

        $this->requestCode(event: $events[5])->assertJsonPath('error.code', 'OTP_RATE_LIMITED');
        $this->assertCount(5, $this->sms->sent);
    }

    public function test_codes_are_only_sent_while_voting_is_open(): void
    {
        $this->event->update(['voting_enabled' => false]);

        $this->requestCode()->assertForbidden()->assertJsonPath('error.code', 'VOTING_CLOSED')->assertJsonPath('error.details.status', 'closed');
        $this->assertCount(0, $this->sms->sent);
    }

    public function test_off_site_visitors_get_no_code(): void
    {
        $this->requestCode(ip: '198.51.100.7')->assertForbidden()->assertJsonPath('error.code', 'OFF_SITE');
        $this->assertCount(0, $this->sms->sent);
    }

    public function test_landlines_and_bad_input_fail_validation(): void
    {
        $this->requestCode(phone: '064123456')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')->assertJsonStructure(['error' => ['details' => ['fields' => ['phone']]]]);
        $this->requestCode(name: 'L')->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->verify('12ab')->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_requesting_a_code_cannot_rename_a_verified_visitor(): void
    {
        $this->requestCode(name: 'Lina Haddad');
        $this->verify($this->sms->lastCodeFor(self::PHONE));
        $this->travel(61)->seconds();

        $this->requestCode(name: 'Someone Else');

        $this->assertSame('Lina Haddad', EventRegistration::sole()->full_name);
        $this->assertSame('Lina Haddad', Visitor::sole()->full_name);
    }

    public function test_verification_is_per_event_and_a_token_only_works_for_its_event(): void
    {
        $token = $this->verifiedToken();
        $other = $this->openEvent('other-event');

        $this->getJson('/api/v1/events/other-event/me', ['Authorization' => 'Bearer '.$token])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.details.reason', 'wrong_event');

        // The same phone must confirm a new code for the other event.
        $this->travel(61)->seconds();
        $this->requestCode(event: $other)->assertAccepted();
        $this->assertSame(2, EventRegistration::count());
        $this->assertNull(EventRegistration::where('event_id', $other->id)->sole()->phone_verified_at);
    }

    public function test_me_lists_votes_and_remaining_categories(): void
    {
        $token = $this->verifiedToken();
        [$first, $second] = Category::factory()->for($this->event)->count(2)->create()->all();
        $exhibitor = Exhibitor::factory()->inCategory($first)->create();
        Vote::create(['event_id' => $this->event->id, 'visitor_id' => Visitor::sole()->id, 'category_id' => $first->id, 'exhibitor_id' => $exhibitor->id]);

        $this->getJson('/api/v1/events/mc2026/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.votes.0.category_id', $first->id)
            ->assertJsonPath('data.votes.0.exhibitor_id', $exhibitor->id)
            ->assertJsonPath('data.remaining_category_ids', [$second->id]);
    }

    public function test_missing_invalid_and_expired_tokens_are_rejected_with_a_reason(): void
    {
        $this->getJson('/api/v1/events/mc2026/me')->assertUnauthorized()->assertJsonPath('error.details.reason', 'missing');
        $this->getJson('/api/v1/events/mc2026/me', ['Authorization' => 'Bearer 1|nope'])->assertJsonPath('error.details.reason', 'invalid');

        $token = $this->verifiedToken();
        $this->travel(13)->hours();
        $this->getJson('/api/v1/events/mc2026/me', ['Authorization' => 'Bearer '.$token])->assertJsonPath('error.details.reason', 'expired');
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->verifiedToken();

        $this->postJson('/api/v1/events/mc2026/auth/logout', [], ['Authorization' => 'Bearer '.$token])->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/events/mc2026/me', ['Authorization' => 'Bearer '.$token])->assertJsonPath('error.details.reason', 'invalid');
    }

    private function openEvent(string $slug): Event
    {
        return Event::factory()->votingOpen()->create([
            'slug' => $slug,
            'name' => $slug === 'mc2026' ? 'Maker Collective 2026' : $slug,
            'allowed_cidrs' => ['203.0.113.0/24'],
        ])->refresh();
    }

    private function requestCode(string $name = 'Lina Haddad', string $phone = self::PHONE, ?Event $event = null, string $ip = self::VENUE_IP): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/events/'.($event ?? $this->event)->slug.'/auth/otp/request', ['full_name' => $name, 'phone' => $phone]);
    }

    private function verify(?string $code, string $phone = self::PHONE, ?Event $event = null): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::VENUE_IP])
            ->postJson('/api/v1/events/'.($event ?? $this->event)->slug.'/auth/otp/verify', ['phone' => $phone, 'code' => $code]);
    }

    private function verifiedToken(): string
    {
        $this->requestCode();

        return $this->verify($this->sms->lastCodeFor(self::PHONE))->json('data.token');
    }
}
