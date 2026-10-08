import { ApiError } from "@/lib/api/client"
import { clearVisitorToken } from "@/lib/api/session"

type Router = { replace: (href: string) => void }

/**
 * Errors every visitor page treats the same way. Returns true when it
 * navigated away (the caller should stop), false when the caller should show
 * the error itself.
 *
 * - OFF_SITE: not on the venue Wi-Fi → "No access" page
 * - UNAUTHENTICATED / UNVERIFIED: token missing, expired or for another event → log in again
 */
export function redirectForError(error: unknown, event: string, router: Router): boolean {
    if (!(error instanceof ApiError)) return false

    switch (error.code) {
        case "OFF_SITE":
            router.replace(`/${event}/no-access`)
            return true
        case "UNAUTHENTICATED":
        case "UNVERIFIED":
            clearVisitorToken(event)
            router.replace(`/${event}/login`)
            return true
        default:
            return false
    }
}

/** A short, visitor-friendly message for an API error. */
export function messageFor(error: unknown): string {
    if (!(error instanceof ApiError)) {
        return "Something went wrong. Please try again."
    }

    switch (error.code) {
        case "VOTING_CLOSED":
            return error.details.status === "scheduled" ? "Voting has not started yet." : "Voting is closed."
        case "OTP_RATE_LIMITED":
        case "TOO_MANY_REQUESTS":
            return `Please wait ${error.retryAfter || "a few"} seconds and try again.`
        case "VALIDATION_FAILED":
            return Object.values(error.fields)[0]?.[0] ?? error.message
        case "EVENT_NOT_FOUND":
            return "This event does not exist or is no longer available."
        default:
            return error.message
    }
}
