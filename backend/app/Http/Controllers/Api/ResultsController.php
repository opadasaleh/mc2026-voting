<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Results\Standings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Live standings for the TV screen (F7, F8). Display token only.
 */
class ResultsController extends Controller
{
    public function __construct(private readonly Standings $standings) {}

    /**
     * GET /events/{event}/results: one snapshot (also the polling fallback).
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json(['data' => $this->snapshot($event)]);
    }

    /**
     * GET /events/{event}/results/stream: Server-Sent Events. A full snapshot on
     * connect and whenever it changes, a heartbeat comment in between. The
     * connection closes after a few minutes (or when the token is revoked) and
     * the browser reconnects by itself; every message is a full snapshot, so no
     * catch-up is needed.
     */
    public function stream(Request $request, Event $event): StreamedResponse
    {
        /** @var PersonalAccessToken $token */
        $token = $request->attributes->get('display_token');

        return response()->stream(function () use ($event, $token) {
            $settings = config('voting.results');
            $deadline = now()->addSeconds($settings['stream_max_seconds']);
            $lastFingerprint = null;
            $lastSentAt = now();

            $this->send("retry: 3000\n\n");

            while (true) {
                $snapshot = $this->snapshot($event);
                $fingerprint = $this->fingerprint($snapshot);

                if ($fingerprint !== $lastFingerprint) {
                    $this->send("event: snapshot\ndata: ".json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n");
                    $lastFingerprint = $fingerprint;
                    $lastSentAt = now();
                } elseif ($lastSentAt->diffInSeconds(now()) >= $settings['stream_heartbeat_seconds']) {
                    $this->send(": heartbeat\n\n");
                    $lastSentAt = now();
                }

                if (now()->gte($deadline) || connection_aborted()) {
                    break;
                }

                Sleep::for($settings['stream_poll_seconds'])->seconds();

                // Stop serving a revoked token or a deactivated event; the reconnect then gets 401 / 404.
                $event->refresh();
                if (! $event->is_active || ! PersonalAccessToken::whereKey($token->getKey())->exists()) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no', // nginx: do not buffer the stream
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Event $event): array
    {
        return [
            ...$this->standings->cachedForEvent($event),
            'voting' => [
                'status' => $event->votingStatus(),
                'opens_at' => $event->opens_at?->toIso8601ZuluString(),
                'closes_at' => $event->closes_at?->toIso8601ZuluString(),
            ],
        ];
    }

    /**
     * Everything except the generation time, so an unchanged tally is not re-sent.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function fingerprint(array $snapshot): string
    {
        unset($snapshot['generated_at']);

        return md5(json_encode($snapshot));
    }

    private function send(string $chunk): void
    {
        echo $chunk;

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
