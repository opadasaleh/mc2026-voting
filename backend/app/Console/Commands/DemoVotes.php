<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Event;
use App\Support\Audit;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Adds demo visitors and votes so the results leaderboard can be shown before
 * real voting, and removes them again with --clear. Demo rows are tagged with
 * the reserved documentation address 192.0.2.1 (RFC 5737), which no real
 * visitor can have, so clearing never touches real data.
 */
class DemoVotes extends Command
{
    public const DEMO_IP = '192.0.2.1';

    protected $signature = 'voting:demo-votes
        {event=mc2026 : The event slug}
        {--visitors=150 : How many demo visitors to add}
        {--clear : Remove the demo visitors and votes instead}';

    protected $description = 'Add (or --clear) demo visitors and votes so the results leaderboard has data';

    private const FIRST_NAMES = ['Lina', 'Omar', 'Sara', 'Yazan', 'Rania', 'Khaled', 'Dana', 'Faris', 'Noor', 'Hamza', 'Leen', 'Zaid', 'Maya', 'Tariq', 'Jana', 'Ahmad', 'Hala', 'Sami', 'Rawan', 'Yousef'];

    private const LAST_NAMES = ['Haddad', 'Khoury', 'Masri', 'Nasser', 'Saleh', 'Qasem', 'Odeh', 'Hamdan', 'Zoubi', 'Majali', 'Tamimi', 'Shami', 'Abbadi', 'Rifai', 'Farah'];

    public function handle(): int
    {
        $event = Event::where('slug', $this->argument('event'))->first();

        if ($event === null) {
            $this->error("No event with slug [{$this->argument('event')}].");

            return self::FAILURE;
        }

        if (app()->isProduction() && ! $this->confirm("This is production. Change demo data for [{$event->slug}]?")) {
            return self::FAILURE;
        }

        return $this->option('clear') ? $this->clear($event) : $this->add($event, max(1, (int) $this->option('visitors')));
    }

    private function add(Event $event, int $count): int
    {
        $categories = Category::where('event_id', $event->getKey())
            ->where('is_active', true)
            ->with(['exhibitors' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Category $category) => $category->exhibitors->isNotEmpty());

        if ($categories->isEmpty()) {
            $this->error('The event has no active categories with active exhibitors.');

            return self::FAILURE;
        }

        // Uneven popularity per exhibitor gives a realistic leaderboard with leaders and close races.
        $weights = [];
        foreach ($categories as $category) {
            foreach ($category->exhibitors as $exhibitor) {
                $weights[$category->id][$exhibitor->id] = random_int(1, 10) ** 2;
            }
        }

        $now = now();
        $visitors = $this->newVisitors($count);

        $votes = DB::transaction(function () use ($event, $categories, $weights, $visitors, $now): int {
            DB::table('visitors')->insert(array_map(fn (array $visitor) => [
                'full_name' => $visitor['name'],
                'phone_encrypted' => Crypt::encryptString($visitor['phone']),
                'phone_hash' => $visitor['hash'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $visitors));

            $ids = DB::table('visitors')->whereIn('phone_hash', array_column($visitors, 'hash'))->pluck('id', 'phone_hash');

            $registrations = [];
            $votes = [];
            foreach ($visitors as $visitor) {
                $visitorId = $ids[$visitor['hash']];
                $verifiedAt = $now->copy()->subMinutes(random_int(5, 180));
                $registrations[] = [
                    'event_id' => $event->getKey(),
                    'visitor_id' => $visitorId,
                    'full_name' => $visitor['name'],
                    'phone_verified_at' => $verifiedAt,
                    'registered_ip' => self::DEMO_IP,
                    'created_at' => $verifiedAt,
                    'updated_at' => $verifiedAt,
                ];

                foreach ($categories as $category) {
                    if (random_int(1, 100) > 85) {
                        continue; // not everyone votes in every category
                    }

                    $votes[] = [
                        'event_id' => $event->getKey(),
                        'visitor_id' => $visitorId,
                        'category_id' => $category->id,
                        'exhibitor_id' => $this->pick($weights[$category->id]),
                        'ip' => self::DEMO_IP,
                        'created_at' => $verifiedAt->copy()->addMinutes(random_int(1, 5)),
                    ];
                }
            }

            DB::table('event_registrations')->insert($registrations);
            foreach (array_chunk($votes, 500) as $chunk) {
                DB::table('votes')->insert($chunk);
            }

            return count($votes);
        });

        Audit::record('demo.votes_added', $event, ['visitors' => $count, 'votes' => $votes]);
        $this->info("Added {$count} demo visitors and {$votes} votes to [{$event->slug}]. Remove them with: php artisan voting:demo-votes {$event->slug} --clear");

        return self::SUCCESS;
    }

    private function clear(Event $event): int
    {
        [$visitors, $votes] = DB::transaction(function () use ($event): array {
            $votes = DB::table('votes')->where('event_id', $event->getKey())->where('ip', self::DEMO_IP)->delete();

            $registrations = DB::table('event_registrations')->where('event_id', $event->getKey())->where('registered_ip', self::DEMO_IP);
            $visitorIds = $registrations->pluck('visitor_id');
            $registrations->delete();

            // Only demo visitors that are not registered for any other event.
            $visitors = DB::table('visitors')
                ->whereIn('id', $visitorIds)
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('event_registrations')->whereColumn('event_registrations.visitor_id', 'visitors.id'))
                ->delete();

            return [$visitors, $votes];
        });

        Audit::record('demo.votes_cleared', $event, ['visitors' => $visitors, 'votes' => $votes]);
        $this->info("Removed {$visitors} demo visitors and {$votes} demo votes from [{$event->slug}].");

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, phone: string, hash: string}>
     */
    private function newVisitors(int $count): array
    {
        $visitors = [];

        while (count($visitors) < $count) {
            $batch = [];
            for ($i = count($visitors); $i < $count; $i++) {
                $phone = '+96279'.str_pad((string) random_int(0, 9_999_999), 7, '0', STR_PAD_LEFT);
                $batch[PhoneNumber::hash($phone)] = [
                    'name' => self::FIRST_NAMES[array_rand(self::FIRST_NAMES)].' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)],
                    'phone' => $phone,
                    'hash' => PhoneNumber::hash($phone),
                ];
            }

            // Never reuse a phone that already belongs to a visitor.
            $taken = DB::table('visitors')->whereIn('phone_hash', array_keys($batch))->pluck('phone_hash')->all();
            foreach (array_diff_key($batch, array_flip($taken), array_column($visitors, null, 'hash')) as $visitor) {
                $visitors[] = $visitor;
            }
        }

        return array_slice($visitors, 0, $count);
    }

    /**
     * @param  array<int, int>  $weights  exhibitor id => weight
     */
    private function pick(array $weights): int
    {
        $roll = random_int(1, array_sum($weights));

        foreach ($weights as $id => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $id;
            }
        }

        return array_key_last($weights);
    }
}
