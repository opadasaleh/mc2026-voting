import { apiFetch, eventPath } from "@/lib/api/client"
import type {
    AccessCheck,
    Category,
    EventInfo,
    Me,
    OtpRequested,
    VisitorToken,
    VoteResult,
} from "@/types/api"

export function getEvent(event: string) {
    return apiFetch<EventInfo>(eventPath(event))
}

export function checkAccess(event: string) {
    return apiFetch<AccessCheck>(eventPath(event, "/access-check"))
}

export function getCategories(event: string) {
    return apiFetch<Category[]>(eventPath(event, "/categories"))
}

/** Also used to resend a code (same body). */
export function requestOtp(event: string, body: { full_name: string; phone: string }) {
    return apiFetch<OtpRequested>(eventPath(event, "/auth/otp/request"), { method: "POST", body })
}

export function verifyOtp(event: string, body: { phone: string; code: string }) {
    return apiFetch<VisitorToken>(eventPath(event, "/auth/otp/verify"), { method: "POST", body })
}

export function getMe(event: string, token: string) {
    return apiFetch<Me>(eventPath(event, "/me"), { token })
}

/** 201 = counted, 200 = the same vote re-sent (safe retry); both resolve. */
export function castVote(event: string, token: string, body: { category_id: number; exhibitor_id: number }) {
    return apiFetch<VoteResult>(eventPath(event, "/votes"), { method: "POST", body, token })
}

export function logout(event: string, token: string) {
    return apiFetch<void>(eventPath(event, "/auth/logout"), { method: "POST", token })
}
