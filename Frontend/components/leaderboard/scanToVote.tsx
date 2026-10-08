"use client"

import { QRCodeSVG } from "qrcode.react"

type ScanToVoteProps = {
    event: string
}

// The voting site's public address. Set NEXT_PUBLIC_SITE_URL when the TV opens the
// site through a different address than phones use (e.g. http://localhost:3000).
function votingUrl(event: string): string {
    const base = (process.env.NEXT_PUBLIC_SITE_URL ?? window.location.origin).replace(/\/$/, "")
    return `${base}/${encodeURIComponent(event)}`
}

// Corner card on the TV: people watching the results can scan and vote straight away.
// Rendered only in the browser (the leaderboard waits for hydration).
export function ScanToVote({ event }: ScanToVoteProps) {
    const url = votingUrl(event)

    return (
        <aside className="fixed bottom-8 right-8 flex items-center gap-5 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-primary/10">
            <QRCodeSVG value={url} size={150} level="M" marginSize={1} title={`Scan to vote: ${url}`} />
            <div className="max-w-44">
                <p className="text-2xl font-bold text-primary">Scan to vote</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Join the event Wi-Fi, scan, and pick your favourites.
                </p>
            </div>
        </aside>
    )
}
