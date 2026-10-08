"use client"
import { Leaderboard } from "@/components/leaderboard/leaderboard";
import Image from "next/image";
import { useEffect, useState } from "react";

const categories = [
    {
        id: 1,
        name: "Best Product Design",
        standings: [
            {
                rank: 1,
                exhibitor_id: 1,
                name: "Ahmad Saleh",
                photo_url: "/images/F1.jpg",
                votes: 1284,
            },
            {
                rank: 2,
                exhibitor_id: 2,
                name: "Sara Ahmad",
                photo_url: "/images/F2.jpg",
                votes: 1210,
            },
            {
                rank: 3,
                exhibitor_id: 3,
                name: "Omar Ali",
                photo_url: "/images/F3.jpg",
                votes: 1156,
            },
        ]
    },

    {
        id: 2,
        name: "Category Two",
        standings: [
            {
                rank: 1,
                exhibitor_id: 1,
                name: "Ahmad Saleh",
                photo_url: "/images/F1.jpg",
                votes: 1284,
            },
            {
                rank: 2,
                exhibitor_id: 2,
                name: "Sara Ahmad",
                photo_url: "/images/F2.jpg",
                votes: 1210,
            },
            {
                rank: 3,
                exhibitor_id: 3,
                name: "Omar Ali",
                photo_url: "/images/F3.jpg",
                votes: 1156,
            },
        ]
    },

    {
        id: 3,
        name: "Category Three",
        standings: [
            {
                rank: 1,
                exhibitor_id: 1,
                name: "Ahmad Saleh",
                photo_url: "/images/F1.jpg",
                votes: 1284,
            },
            {
                rank: 2,
                exhibitor_id: 2,
                name: "Sara Ahmad",
                photo_url: "/images/F2.jpg",
                votes: 1210,
            },
            {
                rank: 3,
                exhibitor_id: 3,
                name: "Omar Ali",
                photo_url: "/images/F3.jpg",
                votes: 1156,
            },
        ]
    },
]

export default function LeaderboardPage() {
    const [activeCategory, setActiveCategory] = useState(0)

    const currentCategory = categories[activeCategory]

    useEffect(() => {
        const interval = setInterval(() => {
            setActiveCategory((current) =>
                (current + 1) % categories.length
            )
        }, 8000)

        return () => clearInterval(interval)
    }, [])

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
                    <span className="h-2.5 w-2.5 rounded-full bg-red-500" />
                    LIVE
                </span>
            </header>

            {/* Current Category */}
            <section className="mt-6 text-center">

                <div>
                    <p className="text-sm font-bold uppercase tracking-[0.35em] text-primary">
                        Category {String(currentCategory.id).padStart(2, "0")}
                    </p>

                    <h1 className="mt-2 text-5xl font-bold uppercase tracking-tight">
                        {currentCategory.name}
                    </h1>
                </div>

                <Leaderboard
                    standings={currentCategory.standings}
                />

            </section>
        </main>
    )
}