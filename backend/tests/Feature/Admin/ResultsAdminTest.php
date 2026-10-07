<?php

namespace Tests\Feature\Admin;

use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Widgets\LiveResults;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\User;
use App\Models\Visitor;
use App\Models\Vote;
use App\Support\Audit;
use App\Support\Csv;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dashboard part B: live results, exports (F13, F14), reset, audit log.
 */
class ResultsAdminTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $admin;

    private Category $category;

    private Exhibitor $exhibitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['slug' => 'mc2026'])->refresh();
        $this->admin = User::factory()->create(['username' => 'admin']);
        $this->category = Category::factory()->for($this->event)->create(['name' => 'People']);
        $this->exhibitor = Exhibitor::factory()->for($this->event)->create(['name' => 'Robo Arm']);
        $this->category->exhibitors()->attach($this->exhibitor->id, ['event_id' => $this->event->id]);

        Filament::setCurrentPanel('admin');
    }

    public function test_the_live_results_widget_shows_the_standings(): void
    {
        $this->vote(Visitor::factory()->create());
        $this->signIn();

        Livewire::test(LiveResults::class)
            ->assertSee('Live results')
            ->assertSee('People')
            ->assertSee('Robo Arm')
            ->assertSee('1 votes from 1 visitors');
    }

    public function test_results_export_lists_every_category_entry_and_is_audited(): void
    {
        $this->vote(Visitor::factory()->create());
        $this->signIn();

        $csv = $this->download(Livewire::test(Dashboard::class)->callAction('exportResults'));

        $this->assertSame(["Category,Rank,Exhibitor,Votes", 'People,1,"Robo Arm",1'], $csv);
        $this->assertTrue(AuditLog::where('action', 'results.exported')->exists());
    }

    public function test_visitor_export_contains_phone_numbers_neutralises_formulas_and_is_audited(): void
    {
        $visitor = Visitor::factory()->create(['phone_encrypted' => '+962791234567']);
        $this->vote($visitor, '=HYPERLINK("http://evil")');
        $this->signIn();

        $csv = $this->download(Livewire::test(Dashboard::class)->callAction('exportVisitors'));

        $this->assertStringStartsWith('"Full name",Phone,', $csv[0]);
        $this->assertStringStartsWith('"\'=HYPERLINK(""http://evil"")",+962791234567,', $csv[1]);
        $this->assertStringEndsWith(',1', $csv[1]);
        $this->assertSame(1, AuditLog::where('action', 'visitors.exported')->sole()->meta['rows']);
    }

    public function test_csv_cells_are_protected_against_formula_injection(): void
    {
        $this->assertSame("'=1+1", Csv::cell('=1+1'));
        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame("'-cmd", Csv::cell('-cmd'));
        $this->assertSame('+962791234567', Csv::cell('+962791234567'));
        $this->assertSame('-5', Csv::cell('-5'));
        $this->assertSame('Lina', Csv::cell('Lina'));
    }

    public function test_results_cannot_be_reset_while_voting_is_open(): void
    {
        $this->event->update(['voting_enabled' => true]);
        $this->signIn();

        Livewire::test(Dashboard::class)->assertActionDisabled('resetResults');
    }

    public function test_reset_requires_the_event_slug(): void
    {
        $this->vote(Visitor::factory()->create());
        $this->signIn();

        Livewire::test(Dashboard::class)
            ->callAction('resetResults', data: ['confirmation' => 'yes'])
            ->assertHasActionErrors(['confirmation']);

        $this->assertSame(1, Vote::count());
    }

    public function test_reset_deletes_only_this_events_votes_and_is_audited(): void
    {
        $this->vote(Visitor::factory()->create());
        $this->vote(Visitor::factory()->create());
        $otherVote = $this->voteInAnotherEvent();
        $this->signIn();

        Livewire::test(Dashboard::class)
            ->callAction('resetResults', data: ['confirmation' => 'mc2026'])
            ->assertHasNoActionErrors();

        $this->assertSame([$otherVote->id], Vote::pluck('id')->all());
        $this->assertSame(2, EventRegistration::where('event_id', $this->event->id)->count(), 'Visitors stay registered.');
        $this->assertSame(2, AuditLog::where('action', 'votes.reset')->sole()->meta['deleted']);
    }

    public function test_the_audit_log_shows_this_event_and_global_entries_only(): void
    {
        $other = Event::factory()->create();
        $mine = Audit::record('voting.opened', $this->event, userId: $this->admin->id);
        $global = Audit::record('admin.login', userId: $this->admin->id);
        $foreign = Audit::record('voting.opened', $other, userId: $this->admin->id);
        $this->signIn();

        Livewire::test(ManageAuditLogs::class)
            ->assertCanSeeTableRecords([$mine, $global])
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertActionDoesNotExist('create');
    }

    public function test_admin_sign_ins_are_audited(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'wrong'])
            ->call('authenticate');
        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'password'])
            ->call('authenticate');

        $this->assertSame('admin', AuditLog::where('action', 'admin.login_failed')->sole()->meta['username']);
        $this->assertSame($this->admin->id, AuditLog::where('action', 'admin.login')->sole()->user_id);
    }

    private function vote(Visitor $visitor, ?string $fullName = null): Vote
    {
        $registration = EventRegistration::factory()->for($this->event)->for($visitor)->create(['full_name' => $fullName ?? $visitor->full_name]);

        return Vote::create([
            'event_id' => $this->event->id,
            'visitor_id' => $registration->visitor_id,
            'category_id' => $this->category->id,
            'exhibitor_id' => $this->exhibitor->id,
        ]);
    }

    private function voteInAnotherEvent(): Vote
    {
        $other = Event::factory()->create();
        $category = Category::factory()->for($other)->create();
        $exhibitor = Exhibitor::factory()->for($other)->create();
        $category->exhibitors()->attach($exhibitor->id, ['event_id' => $other->id]);
        $registration = EventRegistration::factory()->for($other)->create();

        return Vote::create(['event_id' => $other->id, 'visitor_id' => $registration->visitor_id, 'category_id' => $category->id, 'exhibitor_id' => $exhibitor->id]);
    }

    /**
     * @return list<string> CSV lines without the BOM
     */
    private function download(mixed $component): array
    {
        $content = base64_decode($component->effects['download']['content']);

        return array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $content)), fn ($line) => $line !== ''));
    }

    private function signIn(): void
    {
        $this->actingAs($this->admin);
        Filament::setTenant($this->event);
        Filament::bootCurrentPanel();
    }
}
