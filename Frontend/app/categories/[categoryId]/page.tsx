"use client";

import { use, useState } from "react";
import { ExhibitorCard } from "@/components/categories/exhibitorsCard";
import { VoteAlert } from "@/components/categories/voteAlert";
type CategoryPageProps = {
    params: Promise<{
        categoryId: string;
    }>;
};

const exhibitors = [
    {
        id: 12,
        name: "Smart Greenhouse",
        short_description: "Soil-sensing smart greenhouse system.",
        photo_url: "https://avatar.vercel.sh/greenhouse",
    },
    {
        id: 7,
        name: "Robo Arm",
        short_description: "Smart robotic arm project.",
        photo_url: "https://avatar.vercel.sh/robot",
    },
    {
        id: 18,
        name: "Eco System",
        short_description: "A sustainable environmental solution.",
        photo_url: "https://avatar.vercel.sh/eco",
    },
];

export default function CategoryPage({
    params,
}: CategoryPageProps) {
    const { categoryId } = use(params);

    const [selectedExhibitor, setSelectedExhibitor] =
        useState<number | null>(null);

    const selected = exhibitors.find(
        (exhibitor) => exhibitor.id === selectedExhibitor
    );
    

    return (
        <main className="min-h-screen p-6">
            <h1 className="text-4xl font-bold">
                Category {categoryId}
            </h1>

            <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {exhibitors.map((exhibitor) => (
                    <ExhibitorCard
                        key={exhibitor.id}
                        name={exhibitor.name}
                        description={exhibitor.short_description}
                        photoUrl={exhibitor.photo_url}
                        selected={selectedExhibitor === exhibitor.id}
                        onSelect={() => setSelectedExhibitor(exhibitor.id)}
                    />
                ))}
            </div>
            <VoteAlert
                disabled={selectedExhibitor === null}
                exhibitorName={selected?.name ?? ""}
                onConfirm={() => {
                    console.log("Vote:", {
                        category_id: Number(categoryId),
                        exhibitor_id: selectedExhibitor,
                    });
                }}
            />
        </main>
    );
}