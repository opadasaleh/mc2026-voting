// Shapes returned by the Laravel API (docs/03-api-contract.md). Times are ISO-8601 UTC.

export type VotingStatus = "open" | "scheduled" | "closed"

export type Voting = {
    status: VotingStatus
    opens_at: string | null
    closes_at: string | null
}

export type EventInfo = {
    slug: string
    name: string
    description: string | null
    voting: Voting
    categories_count: number
    server_time: string
}

export type AccessCheck = {
    on_site: boolean
    reason: string | null
    venue_wifi_name: string | null
}

export type Exhibitor = {
    id: number
    name: string
    short_description: string | null
    photo_url: string | null
}

export type Category = {
    id: number
    slug: string
    name: string
    description: string | null
    exhibitors: Exhibitor[]
}

export type OtpRequested = {
    expires_in: number
    resend_after: number
}

export type VisitorToken = {
    token: string
    token_type: "Bearer"
    expires_at: string
    visitor: { full_name: string }
}

export type CastVote = {
    category_id: number
    exhibitor_id: number
    voted_at: string
}

export type Me = {
    visitor: { full_name: string }
    votes: CastVote[]
    remaining_category_ids: number[]
}

export type VoteResult = CastVote & {
    remaining_category_ids: number[]
}

export type ApiErrorCode =
    | "UNAUTHENTICATED"
    | "OFF_SITE"
    | "VOTING_CLOSED"
    | "UNVERIFIED"
    | "EVENT_NOT_FOUND"
    | "ALREADY_VOTED"
    | "VALIDATION_FAILED"
    | "INVALID_EXHIBITOR_FOR_CATEGORY"
    | "OTP_INVALID"
    | "OTP_EXPIRED"
    | "OTP_RATE_LIMITED"
    | "TOO_MANY_REQUESTS"
    | "NETWORK_ERROR"
