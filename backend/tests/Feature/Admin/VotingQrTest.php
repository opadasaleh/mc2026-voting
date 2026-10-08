<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Dashboard;
use App\Models\Event;
use App\Models\User;
use App\Support\VotingQr;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The voting QR code on the admin dashboard (F4).
 */
class VotingQrTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        config(['voting.frontend_url' => 'https://vote.example.org']);
        $this->event = Event::factory()->create(['slug' => 'mc2026']);

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
        Filament::setTenant($this->event);
        Filament::bootCurrentPanel();
    }

    public function test_the_code_opens_the_events_voting_page(): void
    {
        $this->assertSame('https://vote.example.org/mc2026', VotingQr::url($this->event));
    }

    public function test_without_frontend_url_the_first_non_localhost_frontend_origin_is_used(): void
    {
        $config = fn (array $env) => $this->loadVotingConfig($env)['frontend_url'];

        $this->assertSame('http://192.168.1.25:3000', $config(['FRONTEND_URLS' => 'http://localhost:3000,http://192.168.1.25:3000']));
        $this->assertSame('http://localhost:3000', $config(['FRONTEND_URLS' => 'http://localhost:3000']));
        $this->assertSame('https://vote.example.org', $config(['FRONTEND_URL' => 'https://vote.example.org/', 'FRONTEND_URLS' => 'http://localhost:3000']));
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, mixed>
     */
    private function loadVotingConfig(array $env): array
    {
        $keys = ['FRONTEND_URL', 'FRONTEND_URLS'];
        $saved = array_map(fn (string $key) => [$key, $_ENV[$key] ?? null, $_SERVER[$key] ?? null], $keys);

        try {
            foreach ($keys as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            }
            foreach ($env as $key => $value) {
                $_ENV[$key] = $_SERVER[$key] = $value;
            }

            return require config_path('voting.php');
        } finally {
            foreach ($saved as [$key, $envValue, $serverValue]) {
                unset($_ENV[$key], $_SERVER[$key]);
                if ($envValue !== null) {
                    $_ENV[$key] = $envValue;
                }
                if ($serverValue !== null) {
                    $_SERVER[$key] = $serverValue;
                }
            }
        }
    }

    public function test_the_preview_shows_the_code_and_the_link(): void
    {
        Livewire::test(Dashboard::class)
            ->mountAction('showQrCode')
            ->assertMountedActionModalSee('https://vote.example.org/mc2026')
            ->assertMountedActionModalSeeHtml('src="data:image/svg+xml;base64,')
            ->assertMountedActionModalDontSee('phones cannot open it');
    }

    public function test_the_svg_and_png_downloads(): void
    {
        $svg = Livewire::test(Dashboard::class)->callAction('downloadQrSvg')->effects['download'];
        $this->assertSame('mc2026-voting-qr.svg', $svg['name']);
        $this->assertStringContainsString('<svg', base64_decode($svg['content']));

        $png = Livewire::test(Dashboard::class)->callAction('downloadQrPng')->effects['download'];
        $this->assertSame('mc2026-voting-qr.png', $png['name']);
        $this->assertStringStartsWith("\x89PNG", base64_decode($png['content']));
    }

    public function test_a_localhost_link_is_flagged(): void
    {
        config(['voting.frontend_url' => 'http://localhost:3000']);

        Livewire::test(Dashboard::class)
            ->mountAction('showQrCode')
            ->assertMountedActionModalSee('phones cannot open it');
    }
}
