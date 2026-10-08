import Image from "next/image"
import type { Standing } from "@/types/results"


type LeaderboardProps = {
    standings: Standing[]
}

export function Leaderboard({ standings }: LeaderboardProps) {
    
    if (standings.length < 3) {
        return (
            <div className="mt-20 text-center">
                <p className="text-xl font-bold">
                    Waiting for results...
                </p>
            </div>
        )
    }
    const first = standings[0]
    const second = standings[1]
    const third = standings[2]

    const remainingCandidates = standings.slice(3)

    return (
        <>
            {/* Top 3 */}
            <div className="mx-auto mt-16 flex max-w-6xl items-end justify-center gap-16">

                {/* Second */}
                <div className="w-72 text-center">
                    <div className="relative mx-auto h-44 w-44 overflow-hidden rounded-full">
                        <Image
                            src={second.photo_url || "/images/default-user.jpg"}
                            alt={second.name}
                            fill
                            className="object-cover"
                        />
                    </div>

                    <p className="mt-5 text-2xl font-bold">
                        {second.name}
                    </p>

                    <p className="mt-1 text-lg">
                        {second.votes.toLocaleString()} Votes
                    </p>

                    <p className="mt-3 text-4xl font-bold">
                        #{second.rank}
                    </p>
                </div>

                {/* First */}
                <div className="w-80 -translate-y-10 text-center">
                    <div className="relative mx-auto h-56 w-56 overflow-hidden rounded-full">
                        <Image
                            src={first.photo_url || "/images/default-user.jpg"}
                            alt={first.name}
                            fill
                            className="object-cover"
                        />
                    </div>

                    <p className="mt-5 text-3xl font-bold">
                        {first.name}
                    </p>

                    <p className="mt-1 text-xl">
                        {first.votes.toLocaleString()} Votes
                    </p>

                    <p className="mt-3 text-5xl font-bold">
                        #{first.rank}
                    </p>
                </div>

                {/* Third */}
                <div className="w-72 text-center">
                    <div className="relative mx-auto h-44 w-44 overflow-hidden rounded-full">
                        <Image
                            src={third.photo_url || "/images/default-user.jpg"}
                            alt={third.name}
                            fill
                            className="object-cover"
                        />
                    </div>

                    <p className="mt-5 text-2xl font-bold">
                        {third.name}
                    </p>

                    <p className="mt-1 text-lg">
                        {third.votes.toLocaleString()} Votes
                    </p>

                    <p className="mt-3 text-4xl font-bold">
                        #{third.rank}
                    </p>
                </div>
            </div>

            {/* Remaining standings */}
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
        </>
    )
}