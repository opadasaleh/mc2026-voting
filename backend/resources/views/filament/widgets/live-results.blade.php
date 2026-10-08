<x-filament-widgets::widget>
    <div wire:poll.5s>
        <x-filament::section
            heading="Live results"
            :description="$results['total_votes'].' votes from '.$results['total_voters'].' visitors · updated '.\Illuminate\Support\Carbon::parse($results['generated_at'])->timezone($timezone)->format('H:i:s')"
        >
            @if ($results['categories'] === [])
                <p>No categories yet.</p>
            @else
                <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));">
                    @foreach ($results['categories'] as $category)
                        <div>
                            <h3 style="font-weight: 600; margin-bottom: 0.5rem;">
                                {{ $category['name'] }}
                                <x-filament::badge color="gray" style="display: inline-flex; margin-left: 0.25rem;">{{ $category['total_votes'] }} votes</x-filament::badge>
                            </h3>

                            <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                                <tbody>
                                    @forelse ($category['standings'] as $row)
                                        <tr style="border-top: 1px solid rgba(127, 127, 127, 0.2);">
                                            <td style="padding: 0.375rem 0.5rem 0.375rem 0; width: 2rem; font-variant-numeric: tabular-nums;">
                                                @if ($row['rank'] === 1 && $row['votes'] > 0)
                                                    <x-filament::badge color="warning">1</x-filament::badge>
                                                @else
                                                    {{ $row['rank'] }}
                                                @endif
                                            </td>
                                            <td style="padding: 0.375rem 0;">{{ $row['name'] }}</td>
                                            <td style="padding: 0.375rem 0; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums;">{{ $row['votes'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td style="padding: 0.375rem 0;">No exhibitors entered.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
