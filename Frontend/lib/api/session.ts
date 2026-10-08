// Per-event browser storage. The visitor token is bound to one event on the
// server, so it is stored per event slug. Every access is wrapped in try/catch:
// storage can be unavailable (private mode, blocked site data).

type StoredToken = { token: string; expires_at: string }

export type PendingLogin = { full_name: string; phone: string; resend_after: number; requested_at: number }

function read<T>(storage: () => Storage, key: string): T | null {
    try {
        const raw = storage().getItem(key)
        return raw ? (JSON.parse(raw) as T) : null
    } catch {
        return null
    }
}

function write(storage: () => Storage, key: string, value: unknown): void {
    try {
        storage().setItem(key, JSON.stringify(value))
    } catch {
        // storage unavailable: the visitor just has to verify again after a reload
    }
}

function remove(storage: () => Storage, key: string): void {
    try {
        storage().removeItem(key)
    } catch {}
}

const local = () => window.localStorage
const session = () => window.sessionStorage

// Visitor token: survives closing the tab, until it expires.
export function getVisitorToken(event: string): string | null {
    const stored = read<StoredToken>(local, `mc:token:${event}`)
    if (!stored || new Date(stored.expires_at).getTime() <= Date.now()) {
        return null
    }
    return stored.token
}

export function saveVisitorToken(event: string, token: string, expiresAt: string): void {
    write(local, `mc:token:${event}`, { token, expires_at: expiresAt })
}

export function clearVisitorToken(event: string): void {
    remove(local, `mc:token:${event}`)
}

// Name + phone between the login and OTP pages (needed again to resend a code).
export function getPendingLogin(event: string): PendingLogin | null {
    return read<PendingLogin>(session, `mc:pending:${event}`)
}

export function savePendingLogin(event: string, pending: PendingLogin): void {
    write(session, `mc:pending:${event}`, pending)
}

export function clearPendingLogin(event: string): void {
    remove(session, `mc:pending:${event}`)
}

// TV display token: given once in the URL (?token=...), remembered for reloads.
export function getDisplayToken(event: string): string | null {
    return read<string>(local, `mc:display:${event}`)
}

export function saveDisplayToken(event: string, token: string): void {
    write(local, `mc:display:${event}`, token)
}

export function clearDisplayToken(event: string): void {
    remove(local, `mc:display:${event}`)
}
