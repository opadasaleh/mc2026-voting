"use client"
import { Leaderboard } from "@/components/leaderboard/leaderboard";
import { ScanToVote } from "@/components/leaderboard/scanToVote";
import Image from "next/image";
import { useParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { subscribeToResults } from "@/lib/api/results";
import { clearDisplayToken, getDisplayToken, saveDisplayToken } from "@/lib/api/session";
import { useHydrated } from "@/lib/useHydrated";
import type { ResultsSnapshot } from "@/types/results";

// TV screen (F7, F8). Open once as /{event}/leaderboard?token=<display token>
// (created in the admin panel under "TV displays"); the token is then remembered.
export default function LeaderboardPage() {
    const { event } = useParams<{ event: string }>()
    const hydrated = useHydrated()
    const [snapshot, setSnapshot] = useState<ResultsSnapshot | null>(null)
    const [isInvalid, setIsInvalid] = useState(false)
    const [isLive, setIsLive] = useState(false)
    const [activeCategory, setActiveCategory] = useState(0)

    // The token from the URL wins over a remembered one; read once after hydration.
    const token = useMemo(() => {
        if (!hydrated) return null
        return new URLSearchParams(window.location.search).get("token") ?? getDisplayToken(event)
    }, [event, hydrated])

    useEffect(() => {
        if (!token) return

        saveDisplayToken(event, token)
        // Keep the token out of the address bar (and out of photos of the TV).
        window.history.replaceState(null, "", window.location.pathname)

        return subscribeToResults(event, token, {
            onSnapshot: setSnapshot,
            onUnauthorized: () => {
                clearDisplayToken(event)
                setIsInvalid(true)
            },
            onConnectionChange: setIsLive,
        })
    }, [event, token])

    const tokenState = !hydrated ? "checking" : !token ? "missing" : isInvalid ? "invalid" : "ok"

    const categories = snapshot?.categories ?? []

    // Rotate through the categories every 8 seconds.
    useEffect(() => {
        if (categories.length < 2) return
        const interval = setInterval(() => {
            setActiveCategory((current) => (current + 1) % categories.length)
        }, 8000)

        return () => clearInterval(interval)
    }, [categories.length])

    const index = categories.length > 0 ? activeCategory % categories.length : 0
    const currentCategory = categories[index]

    if (tokenState === "missing" || tokenState === "invalid") {
        return (
            <main className="flex min-h-screen flex-col items-center justify-center gap-4 p-12 text-center">
                <Image src="/images/ao.png" alt="The Maker Collective 2026" width={300} height={120} />
                <h1 className="text-3xl font-bold">
                    {tokenState === "invalid" ? "Display token invalid" : "Display token needed"}
                </h1>
                <p className="max-w-xl text-lg text-muted-foreground">
                    Ask an admin to create one in the admin panel under <strong>TV displays</strong>, then open
                    this page as <code className="text-primary">/{event}/leaderboard?token=…</code>
                </p>
            </main>
        )
    }

    return (
        <main className="min-h-screen overflow-hidden px-12 py-8">

            {/* Header */}
            <header className="flex items-center justify-between">
                <Image
                    src="/images/ao.png"
                    alt="The Maker Collective 2026"
                    width={300}
                    height={120}
                />

                <span className="flex items-center gap-2 text-sm font-bold tracking-widest">
                    <span className={`h-2.5 w-2.5 rounded-full ${isLive ? "bg-red-500" : "bg-muted-foreground"}`} />
                    {snapshot?.voting.status === "closed" ? "FINAL RESULTS" : isLive ? "LIVE" : "RECONNECTING"}
                </span>
            </header>

            {/* Current Category */}
            {currentCategory ? (
                <section className="mt-6 text-center">

                    <div>
                        <p className="text-sm font-bold uppercase tracking-[0.35em] text-primary">
                            Category {String(index + 1).padStart(2, "0")}
                        </p>

                        <h1 className="mt-2 text-5xl font-bold uppercase tracking-tight">
                            {currentCategory.name}
                        </h1>
                    </div>

                    <Leaderboard
                        standings={currentCategory.standings}
                    />

                </section>
            ) : (
                <p className="mt-20 text-center text-xl font-bold">
                    {snapshot ? "No categories yet." : "Waiting for results..."}
                </p>
            )}

            {snapshot?.voting.status === "open" && <ScanToVote event={event} />}
        </main>
    )
}
