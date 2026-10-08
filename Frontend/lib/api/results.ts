import { API_URL, ApiError, apiFetch, eventPath } from "@/lib/api/client"
import type { ResultsSnapshot } from "@/types/results"

// "poll" for local development with `php artisan serve`, which serves one request at
// a time on Windows: an open stream would block every vote. Production uses "sse".
const USE_POLLING = process.env.NEXT_PUBLIC_RESULTS_TRANSPORT === "poll"

export function getResults(event: string, displayToken: string) {
    return apiFetch<ResultsSnapshot>(eventPath(event, "/results"), { token: displayToken })
}

type Handlers = {
    onSnapshot: (snapshot: ResultsSnapshot) => void
    /** The token is missing, revoked, expired or for another event: show "display token invalid". */
    onUnauthorized: () => void
    onConnectionChange?: (live: boolean) => void
}

/**
 * Live standings over Server-Sent Events. The server sends a full snapshot on
 * connect and on every change, and closes the stream every few minutes; the
 * browser reconnects by itself. If the stream fails for good, fall back to
 * polling every 3 s. Returns a function that stops everything.
 */
export function subscribeToResults(event: string, displayToken: string, handlers: Handlers): () => void {
    let stopped = false
    let pollTimer: ReturnType<typeof setTimeout> | undefined
    let source: EventSource | undefined

    const poll = async () => {
        if (stopped) return
        try {
            handlers.onSnapshot(await getResults(event, displayToken))
            handlers.onConnectionChange?.(true)
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) {
                handlers.onUnauthorized()
                return
            }
            handlers.onConnectionChange?.(false)
        }
        pollTimer = setTimeout(poll, 3000)
    }

    if (USE_POLLING || typeof EventSource === "undefined") {
        poll()
    } else {
        const url = `${API_URL}${eventPath(event, "/results/stream")}?token=${encodeURIComponent(displayToken)}`
        source = new EventSource(url)

        source.addEventListener("snapshot", (message) => {
            handlers.onSnapshot(JSON.parse((message as MessageEvent).data))
            handlers.onConnectionChange?.(true)
        })

        source.onerror = () => {
            if (stopped || !source) return
            if (source.readyState === EventSource.CLOSED) {
                // EventSource gives up on non-200 answers (e.g. 401). Poll once to learn why, then keep polling.
                source.close()
                source = undefined
                handlers.onConnectionChange?.(false)
                poll()
            } else {
                // CONNECTING: a normal reconnect (the server closes streams every few minutes).
                handlers.onConnectionChange?.(false)
            }
        }
    }

    return () => {
        stopped = true
        source?.close()
        clearTimeout(pollTimer)
    }
}
