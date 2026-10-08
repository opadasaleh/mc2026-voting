<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\Exhibitor;
use Illuminate\Database\Seeder;

/**
 * The Maker Collective 2026 event with placeholder categories and mock exhibitors.
 * Voting starts closed and the venue IP list starts empty, so nobody can vote
 * until an admin configures the event.
 */
class Mc2026Seeder extends Seeder
{
    private const CATEGORIES = [
        'people-choice' => "People's Choice",
        'most-innovative' => 'Most Innovative Project',
        'best-craftsmanship' => 'Best Craftsmanship',
    ];

    /** @var array<string, array{0: string, 1: string}> exhibitor => [description, category slug] */
    private const EXHIBITORS = [
        'Smart Greenhouse' => ['Arduino-controlled greenhouse that waters plants from soil-moisture readings.', 'people-choice'],
        'Solar Water Purifier' => ['Low-cost solar still that turns grey water into drinking water.', 'people-choice'],
        "Kids' Coding Blocks" => ['Wooden blocks that teach children to program a small robot.', 'people-choice'],
        'Robo Arm' => ['3D-printed six-axis robotic arm you can drive from your phone.', 'most-innovative'],
        'Braille Tutor' => ['Pocket device that teaches Braille letters with vibrating pins.', 'most-innovative'],
        'Drone Mapping Kit' => ['DIY drone that maps farmland and spots dry patches.', 'most-innovative'],
        'Wooden Synth' => ['Hand-built analog synthesizer in a walnut case.', 'best-craftsmanship'],
        'Upcycled Furniture Studio' => ['Chairs and shelves rebuilt from discarded pallets and doors.', 'best-craftsmanship'],
        'Laser-cut Lamps' => ['A collection of lamps cut from plywood and acrylic.', 'best-craftsmanship'],
    ];

    public function run(): void
    {
        $event = Event::updateOrCreate(['slug' => 'mc2026'], [
            'name' => 'Maker Collective 2026',
            'description' => 'CPF Makerspace community awards.',
            'is_active' => true,
        ]);

        $categoryIds = [];
        $order = 1;

        foreach (self::CATEGORIES as $slug => $name) {
            $categoryIds[$slug] = Category::updateOrCreate(
                ['event_id' => $event->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'description' => 'Placeholder name, to be confirmed by the Makerspace team.',
                    'sort_order' => $order++,
                    'is_active' => true,
                ],
            )->id;
        }

        foreach (self::EXHIBITORS as $name => [$description, $category]) {
            Exhibitor::firstOrCreate(
                ['event_id' => $event->id, 'name' => $name],
                ['category_id' => $categoryIds[$category], 'short_description' => $description, 'is_active' => true],
            );
        }
    }
}
