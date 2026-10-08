import type { ApiErrorCode } from "@/types/api"

// The browser must call the API directly (never through Next.js server code),
// so the API sees the visitor's real IP for the venue Wi-Fi check.
export const API_URL = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, "")

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: ApiErrorCode,
        message: string,
        public readonly details: Record<string, unknown> = {},
    ) {
        super(message)
    }

    /** Field errors from VALIDATION_FAILED, e.g. { phone: ["..."] } */
    get fields(): Record<string, string[]> {
        return (this.details.fields as Record<string, string[]>) ?? {}
    }

    /** Seconds to wait, from 429 responses */
    get retryAfter(): number {
        return Number(this.details.retry_after ?? 0)
    }
}

type Options = {
    method?: "GET" | "POST"
    body?: unknown
    token?: string | null
}

export function eventPath(event: string, path = ""): string {
    return `/api/v1/events/${encodeURIComponent(event)}${path}`
}

export async function apiFetch<T>(path: string, { method = "GET", body, token }: Options = {}): Promise<T> {
    let response: Response

    try {
        response = await fetch(`${API_URL}${path}`, {
            method,
            headers: {
                Accept: "application/json",
                ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
            body: body !== undefined ? JSON.stringify(body) : undefined,
            cache: "no-store",
        })
    } catch {
        throw new ApiError(0, "NETWORK_ERROR", "Unable to connect. Check your connection and try again.")
    }

    if (response.status === 204) {
        return undefined as T
    }

    const json = await response.json().catch(() => null)

    if (!response.ok) {
        const error = json?.error
        throw new ApiError(
            response.status,
            error?.code ?? "NETWORK_ERROR",
            error?.message ?? "Something went wrong. Please try again.",
            error?.details ?? {},
        )
    }

    return json.data as T
}
