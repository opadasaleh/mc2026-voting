<?php

namespace App\Filament\Tenancy;

use App\Models\Event;
use App\Rules\IpOrCidr;
use App\Support\Audit;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-event configuration: voting window, on-site rule (F10, F11) and OTP policy.
 */
class EditEventSettings extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Event settings';
    }

    public function form(Schema $schema): Schema
    {
        $timezone = config('voting.display_timezone');

        return $schema
            ->components([
                Section::make('Event')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->alphaDash()
                            // Reserved by the admin panel's own URLs (/admin/new, /admin/login, ...).
                            ->notIn(['new', 'login', 'logout', 'multi-factor-authentication'])
                            ->maxLength(64)
                            ->unique(Event::class, 'slug', ignoreRecord: true)
                            ->helperText('Used in the visitor link and QR code. Changing it breaks printed QR codes.'),
                        Textarea::make('description')->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Visible to visitors')
                            ->helperText('Off = the event is hidden from the public API (archive).'),
                    ]),

                Section::make('Voting window')
                    ->description("Voting is open only while the switch is on and now is inside the window (if set). Times are {$timezone}.")
                    ->columns(3)
                    ->schema([
                        Toggle::make('voting_enabled')->label('Voting open')->inline(false),
                        DateTimePicker::make('opens_at')->label('Opens at (optional)')->timezone($timezone)->seconds(false),
                        DateTimePicker::make('closes_at')
                            ->label('Closes at (optional)')
                            ->timezone($timezone)
                            ->seconds(false)
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (filled($value) && filled($get('opens_at')) && Carbon::parse($value)->lte(Carbon::parse($get('opens_at')))) {
                                    $fail('The closing time must be after the opening time.');
                                }
                            }),
                    ]),

                Section::make('On-site access')
                    ->description('Who counts as "at the venue". Wi-Fi only is the strictest: it cannot be faked from a phone.')
                    ->schema([
                        Select::make('access_mode')
                            ->label('Rule')
                            ->required()
                            ->native(false)
                            ->live()
                            ->options([
                                'ip' => 'Venue Wi-Fi only (recommended)',
                                'either' => 'Venue Wi-Fi OR inside the geofence',
                                'both' => 'Venue Wi-Fi AND inside the geofence',
                                'geo' => 'Geofence only (spoofable, not recommended)',
                            ]),
                        TagsInput::make('allowed_cidrs')
                            ->label('Venue Wi-Fi public IPs')
                            ->placeholder('203.0.113.0/24')
                            ->helperText('Public IPv4 and IPv6 addresses or ranges of the venue Wi-Fi. Empty = nobody passes the Wi-Fi check.')
                            ->nestedRecursiveRules([new IpOrCidr])
                            ->suffixAction(
                                Action::make('useMyIp')
                                    ->icon(Heroicon::OutlinedSignal)
                                    ->tooltip('Add the IP I am connected from (use while on the venue Wi-Fi)')
                                    ->action(function (Get $get, Set $set): void {
                                        $set('allowed_cidrs', array_values(array_unique([...($get('allowed_cidrs') ?? []), request()->ip()])));
                                    }),
                            ),
                        TextInput::make('venue_wifi_name')
                            ->label('Venue Wi-Fi name')
                            ->maxLength(255)
                            ->helperText('Shown on the visitors\' "No access" page. Display only; never checked.'),
                        Grid::make(3)
                            ->visible(fn (Get $get): bool => $get('access_mode') !== 'ip')
                            ->schema([
                                TextInput::make('geofence.lat')->label('Latitude')->numeric()->minValue(-90)->maxValue(90)->required(),
                                TextInput::make('geofence.lng')->label('Longitude')->numeric()->minValue(-180)->maxValue(180)->required(),
                                TextInput::make('geofence.radius_m')->label('Radius (m)')->numeric()->minValue(10)->maxValue(5000)->required(),
                            ]),
                    ]),

                Section::make('OTP policy')
                    ->columns(3)
                    ->schema([
                        TextInput::make('otp_ttl_seconds')->label('Code valid for (s)')->numeric()->integer()->minValue(60)->maxValue(1800)->required(),
                        TextInput::make('otp_max_attempts')->label('Wrong attempts allowed')->numeric()->integer()->minValue(1)->maxValue(10)->required(),
                        TextInput::make('otp_resend_cooldown_seconds')->label('Resend cooldown (s)')->numeric()->integer()->minValue(15)->maxValue(600)->required(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data);
        $changed = array_keys($record->getDirty());
        $record->save();

        if ($changed !== []) {
            Audit::record('event.settings_updated', $record, ['changed' => $changed]);
        }

        return $record;
    }
}
