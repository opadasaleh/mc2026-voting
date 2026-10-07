<?php

namespace App\Filament\Tenancy;

use App\Models\Event;
use App\Support\Audit;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Creates a new voting event. It starts closed, Wi-Fi only, with no venue IPs,
 * so nobody can vote until it is configured in Event settings.
 */
class RegisterEvent extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'New event';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->alphaDash()
                    // Reserved by the admin panel's own URLs (/admin/new, /admin/login, ...).
                    ->notIn(['new', 'login', 'logout', 'multi-factor-authentication'])
                    ->maxLength(64)
                    ->unique(Event::class, 'slug')
                    ->helperText('Used in the visitor link and the QR code.'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Event
    {
        $event = Event::create($data);

        Audit::record('event.created', $event, ['slug' => $event->slug]);

        return $event;
    }
}
