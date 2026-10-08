"use client"

import { useCallback, useEffect, useState } from "react"
import { OTPInput, REGEXP_ONLY_DIGITS, type SlotProps } from "input-otp"
import { ArrowRight, Check, Loader2, RefreshCw } from "lucide-react"

import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { useParams, useRouter } from "next/navigation"
import { ApiError } from "@/lib/api/client"
import { messageFor, redirectForError } from "@/lib/api/errors"
import { clearPendingLogin, getPendingLogin, savePendingLogin, saveVisitorToken } from "@/lib/api/session"
import { requestOtp, verifyOtp } from "@/lib/api/voting"

const CODE_LENGTH = 6
// Fallback only: the server says how long to wait (resend_after / retry_after).
const RESEND_SECONDS = 60

type Status = "idle" | "loading" | "error" | "success"

// each box gets its own chart color; on success they all turn green
const SLOT_COLORS = [
    "var(--chart-1, #7f32d9)",
    "var(--chart-3, #4a68d8)",
    "var(--chart-4, #74dccf)",
    "var(--chart-2, #f8d749)",
    "var(--chart-3, #4a68d8)",
    "var(--chart-1, #7f32d9)",
]
const SUCCESS = "var(--success, #22c55e)"
const ERROR = "var(--chart-5, #a52a3a)"

function Slot({
    char,
    isActive,
    hasFakeCaret,
    index,
    status,
}: SlotProps & { index: number; status: Status }) {
    const color =
        status === "success" ? SUCCESS : status === "error" ? ERROR : SLOT_COLORS[index]

    return (
        <div
            className={cn(
                "otp-slot",
                char && "is-filled",
                isActive && "is-active",
                status === "error" && "is-error",
                status === "success" && "is-success"
            )}
            style={
                {
                    "--c": color,
                    "--d": `${index * 80}ms`,
                } as React.CSSProperties
            }
        >
            <span className="otp-fill" />
            {char && <span key={`r-${char}`} className="otp-ripple" />}
            {char && (
                <span key={`d-${char}`} className="otp-digit">
                    {char}
                </span>
            )}
            {hasFakeCaret && <span className="otp-caret" />}
        </div>
    )
}

// The resend timer starts where the server said, minus the time already spent.
function initialSeconds(event: string): number {
    const pending = getPendingLogin(event)
    if (!pending) return RESEND_SECONDS
    const elapsed = Math.floor((Date.now() - pending.requested_at) / 1000)
    return Math.max(0, pending.resend_after - elapsed)
}

// Rendered only in the browser (the OTP page waits for hydration), so storage can be read on mount.
export function InputOTPForm() {
    const { event } = useParams<{ event: string }>()
    const [otp, setOtp] = useState("")
    const [seconds, setSeconds] = useState(() => initialSeconds(event))
    const [status, setStatus] = useState<Status>("idle")
    const [message, setMessage] = useState("")
    const router = useRouter()

    useEffect(() => {
        if (seconds <= 0) return
        const t = setTimeout(() => setSeconds((s) => s - 1), 1000)
        return () => clearTimeout(t)
    }, [seconds])

    const submit = useCallback(
        async (code: string) => {
            if (code.length !== CODE_LENGTH || status === "loading" || status === "success") return
            const pending = getPendingLogin(event)
            if (!pending) {
                router.replace(`/${event}/login`)
                return
            }

            setStatus("loading")
            setMessage("")

            try {
                const response = await verifyOtp(event, { phone: pending.phone, code })

                saveVisitorToken(event, response.token, response.expires_at)
                clearPendingLogin(event)
                setStatus("success")

                setTimeout(() => {
                    router.push(`/${event}/categories`)
                }, 1200)
            } catch (err) {
                if (redirectForError(err, event, router)) return

                setStatus("error")
                setOtp("")

                if (err instanceof ApiError && err.code === "OTP_INVALID") {
                    const left = Number(err.details.attempts_remaining ?? 0)
                    setMessage(`That code isn't right. ${left} ${left === 1 ? "try" : "tries"} left.`)
                } else if (err instanceof ApiError && err.code === "OTP_EXPIRED") {
                    setMessage("This code has expired. Tap Resend to get a new one.")
                    setSeconds(0)
                } else {
                    setMessage(messageFor(err))
                }
            }
        },
        [event, router, status]
    )

    function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault()
        submit(otp)
    }

    async function handleResend() {
        if (seconds > 0 || status === "loading" || status === "success") return

        const pending = getPendingLogin(event)
        if (!pending) {
            router.replace(`/${event}/login`)
            return
        }

        setOtp("")
        setStatus("loading")
        setMessage("")

        try {
            const result = await requestOtp(event, { full_name: pending.full_name, phone: pending.phone })
            savePendingLogin(event, { ...pending, resend_after: result.resend_after, requested_at: Date.now() })
            setSeconds(result.resend_after)
            setStatus("idle")
            setMessage("A new code is on its way.")
        } catch (err) {
            if (redirectForError(err, event, router)) return
            setStatus("error")
            if (err instanceof ApiError && err.retryAfter > 0) {
                setSeconds(err.retryAfter)
            }
            setMessage(messageFor(err))
        }
    }

    const isSuccess = status === "success"
    const isError = status === "error"
    const locked = status === "loading" || isSuccess
    const mm = String(Math.floor(seconds / 60)).padStart(2, "0")
    const ss = String(seconds % 60).padStart(2, "0")

    return (
        <form onSubmit={handleSubmit} className="flex flex-col items-center gap-6">
            <div className={cn("otp-stage", isError && "otp-shake")} key={isError ? "err" : "ok"}>
                <OTPInput
                    maxLength={CODE_LENGTH}
                    value={otp}
                    onChange={(value) => {
                        setOtp(value)
                        if (status === "error") setStatus("idle")
                    }}
                    onComplete={submit}
                    disabled={locked}
                    pattern={REGEXP_ONLY_DIGITS}
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    autoFocus
                    aria-label="Verification code"
                    containerClassName="flex items-center"
                    render={({ slots }) => (
                        <div className="flex items-center gap-2 sm:gap-2.5">
                            {slots.slice(0, 3).map((slot, i) => (
                                <Slot key={i} {...slot} index={i} status={status} />
                            ))}
                            <span className="otp-dash" aria-hidden />
                            {slots.slice(3).map((slot, i) => (
                                <Slot key={i + 3} {...slot} index={i + 3} status={status} />
                            ))}
                        </div>
                    )}
                />
            </div>

            <p
                role="status"
                className="min-h-5 text-center text-sm font-medium transition-colors"
                style={{
                    color: isSuccess ? SUCCESS : isError ? ERROR : "var(--muted-foreground)",
                }}
            >
                {isSuccess
                    ? "Code verified. You're in."
                    : message || (isError
                        ? "That code isn't right. Check it and try again."
                        : "Paste works too.")}
            </p>

            <Button
                type="submit"
                size="lg"
                disabled={otp.length !== CODE_LENGTH || locked}
                className={cn(
                    "group/btn h-12 w-full rounded-xl text-base transition-colors duration-300",
                    isSuccess && "otp-btn-success disabled:opacity-100"
                )}
            >
                {status === "loading" ? (
                    <>
                        <Loader2 className="size-4 animate-spin" />
                        Verifying
                    </>
                ) : isSuccess ? (
                    <>
                        <Check className="size-5 otp-check" />
                        Verified
                    </>
                ) : (
                    <>
                        Verify
                        <ArrowRight className="size-4 transition-transform group-hover/btn:translate-x-0.5" />
                    </>
                )}
            </Button>

            <button
                type="button"
                onClick={handleResend}
                disabled={seconds > 0 || locked}
                className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors enabled:hover:text-foreground disabled:cursor-not-allowed"
            >
                <RefreshCw className="size-3.5" />
                {seconds > 0 ? (
                    <span>
                        Resend code in{" "}
                        <span className="tabular-nums">
                            {mm}:{ss}
                        </span>
                    </span>
                ) : (
                    <span className="font-medium text-primary">Resend code</span>
                )}
            </button>

            <style>{`
                .otp-stage { perspective: 400px; }

                .otp-slot {
                    position: relative;
                    display: flex;
                    height: 3.5rem;
                    width: 2.75rem;
                    align-items: center;
                    justify-content: center;
                    border-radius: 0.75rem;
                    border: 1.5px solid var(--border);
                    background: var(--background);
                    font-size: 1.5rem;
                    font-weight: 700;
                    font-variant-numeric: tabular-nums;
                    transition: border-color .25s, box-shadow .25s, transform .25s;
                }
                @media (min-width: 640px) {
                    .otp-slot { height: 4rem; width: 3rem; }
                }

                /* color fills up from the bottom when a digit lands */
                .otp-fill {
                    position: absolute;
                    inset: 0;
                    border-radius: inherit;
                    background: color-mix(in srgb, var(--c) 16%, transparent);
                    transform: scaleY(0);
                    transform-origin: bottom;
                    transition: transform .35s cubic-bezier(.2,.8,.2,1), background .3s;
                }
                .otp-slot.is-filled { border-color: var(--c); animation: otp-bump .35s ease-out; }
                .otp-slot.is-filled .otp-fill { transform: scaleY(1); }

                .otp-slot.is-active {
                    border-color: var(--c);
                    box-shadow: 0 0 0 4px color-mix(in srgb, var(--c) 20%, transparent);
                    transform: translateY(-3px);
                }

                /* digit flips in */
                .otp-digit {
                    position: relative;
                    animation: otp-flip .4s cubic-bezier(.2,.9,.3,1.3);
                }
                /* ring expands out of the box */
                .otp-ripple {
                    position: absolute;
                    inset: -1.5px;
                    border-radius: inherit;
                    border: 2px solid var(--c);
                    pointer-events: none;
                    animation: otp-ripple .55s ease-out forwards;
                }
                .otp-caret {
                    position: absolute;
                    height: 1.5rem;
                    width: 2px;
                    border-radius: 9999px;
                    background: var(--c);
                    animation: otp-blink 1s steps(2, start) infinite;
                }
                .otp-dash {
                    margin: 0 .25rem;
                    height: 2px;
                    width: .75rem;
                    border-radius: 9999px;
                    background: var(--border);
                }

                /* error */
                .otp-slot.is-error .otp-digit { color: var(--c); }
                .otp-shake { animation: otp-shake .4s ease-in-out; }

                /* success: wave of green across the boxes */
                .otp-slot.is-success {
                    border-color: var(--c);
                    box-shadow: 0 0 0 4px color-mix(in srgb, var(--c) 20%, transparent);
                    transition-delay: var(--d);
                    animation: otp-success .6s cubic-bezier(.3,1.4,.5,1) var(--d) both;
                }
                .otp-slot.is-success .otp-fill { background: color-mix(in srgb, var(--c) 22%, transparent); transition-delay: var(--d); }
                .otp-slot.is-success .otp-digit { color: var(--c); animation: none; }

                .otp-btn-success,
                .otp-btn-success:hover {
                    background: var(--success, #22c55e);
                    color: #fff;
                }
                .otp-check { animation: otp-flip .5s cubic-bezier(.2,.9,.3,1.4); }

                @keyframes otp-flip {
                    from { opacity: 0; transform: rotateX(-90deg) scale(.5); }
                    to { opacity: 1; transform: none; }
                }
                @keyframes otp-ripple {
                    from { opacity: .9; transform: scale(1); }
                    to { opacity: 0; transform: scale(1.5); }
                }
                @keyframes otp-bump {
                    0% { transform: scale(1); }
                    40% { transform: scale(1.12); }
                    100% { transform: scale(1); }
                }
                @keyframes otp-blink { to { visibility: hidden; } }
                @keyframes otp-shake {
                    0%, 100% { transform: translateX(0); }
                    20% { transform: translateX(-8px); }
                    40% { transform: translateX(7px); }
                    60% { transform: translateX(-5px); }
                    80% { transform: translateX(3px); }
                }
                @keyframes otp-success {
                    0% { transform: translateY(0) scale(1); }
                    40% { transform: translateY(-12px) scale(1.1); }
                    100% { transform: translateY(0) scale(1); }
                }

                @media (prefers-reduced-motion: reduce) {
                    .otp-slot, .otp-fill, .otp-digit, .otp-ripple, .otp-shake, .otp-check { animation: none !important; transition: none !important; }
                }
            `}</style>
        </form>
    )
}