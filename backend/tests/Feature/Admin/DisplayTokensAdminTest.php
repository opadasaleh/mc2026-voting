<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\DisplayTokens;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "TV displays" page: create, show once, revoke (F7).
 */
class DisplayTokensAdminTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['slug' => 'mc2026']);
        $this->admin = User::factory()->create(['username' => 'admin']);

        Filament::setCurrentPanel('admin');
    }

    public function test_an_admin_creates_a_display_token_that_reads_this_events_results(): void
    {
        $this->signIn();

        $page = Livewire::test(DisplayTokens::class)
            ->callAction('createToken', data: ['name' => 'Main hall TV', 'expires_in_days' => 7])
            ->assertHasNoActionErrors()
            ->assertSee('Copy this token now');

        $token = PersonalAccessToken::sole();
        $this->assertTrue($token->tokenable->is($this->event));
        $this->assertSame(['results:read', 'event:'.$this->event->id], $token->abilities);
        $this->assertTrue($token->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame('Main hall TV', AuditLog::where('action', 'display_token.created')->sole()->meta['name']);

        $plainToken = $page->get('plainToken');
        $this->getJson('/api/v1/events/mc2026/results', ['Authorization' => 'Bearer '.$plainToken])->assertOk();

        $page->callAction(TestAction::make('dismissToken')->schemaComponent('newToken', schema: 'content'))
            ->assertSet('plainToken', null)
            ->assertDontSee('Copy this token now');
    }

    public function test_the_page_lists_only_this_events_tokens(): void
    {
        $other = Event::factory()->create();
        $foreign = $other->createDisplayToken('Other TV')->accessToken;
        $mine = $this->event->createDisplayToken('Main hall TV')->accessToken;
        $this->signIn();

        Livewire::test(DisplayTokens::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertDontSee($mine->token);
    }

    public function test_revoking_a_token_is_audited(): void
    {
        $token = $this->event->createDisplayToken('Main hall TV')->accessToken;
        $this->signIn();

        Livewire::test(DisplayTokens::class)->callTableAction('revoke', $token);

        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertSame($token->id, AuditLog::where('action', 'display_token.revoked')->sole()->meta['token_id']);
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin);
        Filament::setTenant($this->event);
        Filament::bootCurrentPanel();
    }
}
