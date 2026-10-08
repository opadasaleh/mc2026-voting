import Image from "next/image"
import type { Standing } from "@/types/results"


type LeaderboardProps = {
    standings: Standing[]
}

type PodiumSize = "first" | "other"

// Photos come from the API's storage, so they are shown as-is (no Next.js optimisation).
function PodiumPlace({ standing, size }: { standing?: Standing; size: PodiumSize }) {
    const isFirst = size === "first"

    return (
        <div className={`${isFirst ? "w-80 -translate-y-10" : "w-72"} text-center`}>
            <div className={`relative mx-auto ${isFirst ? "h-56 w-56" : "h-44 w-44"} overflow-hidden rounded-full bg-primary/5`}>
                {standing && (
                    <Image
                        src={standing.photo_url || "/images/default-user.jpg"}
                        alt={standing.name}
                        fill
                        unoptimized
                        className="object-cover"
                    />
                )}
            </div>

            <p className={`mt-5 ${isFirst ? "text-3xl" : "text-2xl"} font-bold`}>
                {standing?.name ?? "—"}
            </p>

            <p className={`mt-1 ${isFirst ? "text-xl" : "text-lg"}`}>
                {standing ? `${standing.votes.toLocaleString()} Votes` : " "}
            </p>

            <p className={`mt-3 ${isFirst ? "text-5xl" : "text-4xl"} font-bold`}>
                {standing ? `#${standing.rank}` : " "}
            </p>
        </div>
    )
}

export function Leaderboard({ standings }: LeaderboardProps) {

    if (standings.length === 0) {
        return (
            <div className="mt-20 text-center">
                <p className="text-xl font-bold">
                    Waiting for results...
                </p>
            </div>
        )
    }

    const [first, second, third] = standings
    const remainingCandidates = standings.slice(3)

    return (
        <>
            {/* Top 3 (a category may have fewer than 3 exhibitors) */}
            <div className="mx-auto mt-16 flex max-w-6xl items-end justify-center gap-16">
                <PodiumPlace standing={second} size="other" />
                <PodiumPlace standing={first} size="first" />
                <PodiumPlace standing={third} size="other" />
            </div>

            {/* Remaining standings */}
            {remainingCandidates.length > 0 && (
                <div className="mx-auto mt-8 max-w-7xl border-t border-primary/20 pt-6">
                    <div className="grid grid-cols-6 gap-x-8 gap-y-5">
                        {remainingCandidates.map((candidate) => (
                            <div
                                key={candidate.exhibitor_id}
                                className="flex items-center gap-3 text-left"
                            >
                                <span className="min-w-10 text-2xl font-bold text-primary/50">
                                    #{candidate.rank}
                                </span>

                                <div className="min-w-0">
                                    <p className="truncate text-xl font-bold">
                                        {candidate.name}
                                    </p>

                                    <p className="text-base text-primary/70">
                                        {candidate.votes.toLocaleString()} Votes
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </>
    )
}
