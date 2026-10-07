<?php

namespace Tests\Feature\Admin;

use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Exhibitors\Pages\ManageExhibitors;
use App\Filament\Tenancy\EditEventSettings;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\User;
use App\Models\Vote;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Re-read so database defaults (OTP policy etc.) are present, as in the real panel.
        $this->event = Event::factory()->create(['slug' => 'mc2026'])->refresh();
        $this->admin = User::factory()->create(['username' => 'admin']);

        Filament::setCurrentPanel('admin');
    }

    public function test_admins_sign_in_with_username_and_password(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_a_wrong_password_is_rejected_on_the_username_field(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);

        $this->assertGuest();
    }

    public function test_the_panel_requires_mfa_to_be_set_up_first(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/mc2026')
            ->assertRedirectContains('/admin/multi-factor-authentication/set-up');
    }

    public function test_categories_are_scoped_to_the_current_event(): void
    {
        // Created before signing in: while a tenant is active, Filament assigns new records to it.
        $other = Category::factory()->create();
        $mine = Category::factory()->for($this->event)->create();
        $this->signIn();

        Livewire::test(ManageCategories::class)
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_a_new_category_belongs_to_the_current_event(): void
    {
        $this->signIn();

        Livewire::test(ManageCategories::class)
            ->callAction('create', data: ['name' => 'Best Design', 'slug' => 'best-design', 'sort_order' => 1, 'is_active' => true])
            ->assertHasNoActionErrors();

        $this->assertSame($this->event->id, Category::where('slug', 'best-design')->sole()->event_id);
    }

    public function test_a_category_slug_is_unique_within_the_event(): void
    {
        $this->signIn();
        Category::factory()->for($this->event)->create(['slug' => 'taken']);

        Livewire::test(ManageCategories::class)
            ->callAction('create', data: ['name' => 'Taken', 'slug' => 'taken', 'sort_order' => 1])
            ->assertHasActionErrors(['slug']);
    }

    public function test_new_categories_are_blocked_once_the_event_has_three(): void
    {
        Category::factory()->for($this->event)->count(2)->create();
        $this->signIn();

        Livewire::test(ManageCategories::class)->assertActionEnabled('create');

        Category::factory()->for($this->event)->create();

        Livewire::test(ManageCategories::class)->assertActionDisabled('create');
    }

    public function test_an_exhibitor_is_created_in_one_category_of_the_current_event(): void
    {
        $category = Category::factory()->for($this->event)->create();
        $this->signIn();

        Livewire::test(ManageExhibitors::class)
            ->callAction('create', data: [
                'name' => 'Robo Arm',
                'short_description' => 'A robotic arm.',
                'category_id' => $category->id,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $exhibitor = Exhibitor::where('name', 'Robo Arm')->sole();
        $this->assertSame($this->event->id, $exhibitor->event_id);
        $this->assertSame($category->id, $exhibitor->category_id);
    }

    public function test_the_category_of_an_exhibitor_with_votes_is_locked(): void
    {
        $category = Category::factory()->for($this->event)->create();
        $spare = Category::factory()->for($this->event)->create();
        $exhibitor = Exhibitor::factory()->inCategory($category)->create();
        $this->signIn();
        $registration = EventRegistration::factory()->for($this->event)->create();
        Vote::create([
            'event_id' => $this->event->id,
            'visitor_id' => $registration->visitor_id,
            'category_id' => $category->id,
            'exhibitor_id' => $exhibitor->id,
        ]);

        Livewire::test(ManageExhibitors::class)
            ->callAction(TestAction::make('edit')->table($exhibitor), data: ['category_id' => $spare->id])
            ->assertHasNoActionErrors();

        $this->assertSame($category->id, $exhibitor->fresh()->category_id, 'The disabled field is not saved.');
    }

    public function test_event_settings_reject_invalid_venue_ips(): void
    {
        $this->signIn();

        Livewire::test(EditEventSettings::class)
            ->fillForm(['allowed_cidrs' => ['203.0.113.0/24', 'not-an-ip', '10.0.0.0/33']])
            ->call('save')
            ->assertHasFormErrors(['allowed_cidrs.1', 'allowed_cidrs.2']);
    }

    public function test_event_settings_save_venue_ips_and_are_audited(): void
    {
        $this->signIn();

        Livewire::test(EditEventSettings::class)
            ->fillForm(['allowed_cidrs' => ['203.0.113.0/24', '2001:db8:1::/48'], 'venue_wifi_name' => 'MC2026-Guest'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->event->refresh();
        $this->assertSame(['203.0.113.0/24', '2001:db8:1::/48'], $this->event->allowed_cidrs);
        $this->assertSame('MC2026-Guest', $this->event->venue_wifi_name);

        $log = AuditLog::where('action', 'event.settings_updated')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertContains('allowed_cidrs', $log->meta['changed']);
    }

    public function test_event_slugs_used_by_the_panel_are_reserved(): void
    {
        $this->signIn();

        Livewire::test(EditEventSettings::class)
            ->fillForm(['slug' => 'new'])
            ->call('save')
            ->assertHasFormErrors(['slug']);
    }

    public function test_voting_is_opened_and_closed_from_the_dashboard_and_audited(): void
    {
        $this->signIn();

        Livewire::test(Dashboard::class)
            ->assertActionHidden('closeVoting')
            ->callAction('openVoting');
        $this->assertTrue($this->event->refresh()->voting_enabled);

        Livewire::test(Dashboard::class)
            ->assertActionHidden('openVoting')
            ->callAction('closeVoting');
        $this->assertFalse($this->event->refresh()->voting_enabled);

        $this->assertSame(['voting.opened', 'voting.closed'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin);
        Filament::setTenant($this->event);
        Filament::bootCurrentPanel();
    }
}
