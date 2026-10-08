"use client";

import { use, useState } from "react";
import { ExhibitorCard } from "@/components/categories/exhibitorsCard";
import { VoteAlert } from "@/components/categories/voteAlert";
import { useRouter } from "next/navigation";

type CategoryPageProps = {
    params: Promise<{
        categoryId: string;
    }>;
};

type Exhibitor = {
    id: number;
    name: string;
    short_description: string;
    photo_url: string;
};

type Category = {
    id: number;
    slug: string;
    name: string;
    description: string;
    exhibitors: Exhibitor[];
};

const mockCategory: Category = {
    id: 1,
    slug: "category-one",
    name: "Category One",
    description: "Category description goes here.",
    exhibitors: Array.from({ length: 25 }, (_, index) => ({
        id: index + 1,
        name: `Exhibitor ${index + 1}`,
        short_description: `Description for exhibitor ${index + 1}.`,
        photo_url: `https://avatar.vercel.sh/exhibitor-${index + 1}`,
    })),
};

export default function CategoryPage({
    params,
}: CategoryPageProps) {
    
    const { categoryId } = use(params);

    const [selectedExhibitor, setSelectedExhibitor] =
        useState<number | null>(null);
    
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState("");
    
    const [isSubmitting, setIsSubmitting] = useState(false);
    const router = useRouter();
    
    const [category, setCategory] = useState<Category | null>(
        mockCategory
    );
    
    const [currentPage, setCurrentPage] = useState(1);
    const exhibitorsPerPage = 9;
    
    const selected = category?.exhibitors.find(
        (exhibitor) => exhibitor.id === selectedExhibitor
    );
    
    const totalExhibitors = category?.exhibitors.length ?? 0;
    
    const totalPages = Math.ceil(
        totalExhibitors / exhibitorsPerPage
    );
    
    const startIndex = (currentPage - 1) * exhibitorsPerPage;
    

    const currentExhibitors =
        category?.exhibitors.slice(
            startIndex,
            startIndex + exhibitorsPerPage
        ) ?? [];

    if (isLoading) {
        return (
            <main className="flex min-h-screen items-center justify-center">
                <p className="text-lg font-medium">
                    Loading exhibitors...
                </p>
            </main>
        );
    }

    if (error) {
        return (
            <main className="flex min-h-screen items-center justify-center p-6">
                <p className="text-center text-red-500">
                    {error}
                </p>
            </main>
        );
    }

    
    const handleConfirmVote = async () => {
        if (selectedExhibitor === null || isSubmitting)
            return;

        setIsSubmitting(true);
        setError("");

        try {
            // TODO: API
            // POST /events/{event}/votes
            //
            // body:
            // {
            //     category_id: Number(categoryId),
            //     exhibitor_id: selectedExhibitor,
            //     lat: ...,
            //     lng: ...
            // }

            // Temporary mock until API integration
            const votedCategories: number[] = JSON.parse(
                sessionStorage.getItem("votedCategories") || "[]"
            );

            if (!votedCategories.includes(Number(categoryId))) {
                votedCategories.push(Number(categoryId));
            }

            sessionStorage.setItem(
                "votedCategories",
                JSON.stringify(votedCategories)
            );

            router.push("/categories");
        } catch {
            setError("Failed to submit vote. Please try again.");
        } finally {
            setIsSubmitting(false);
        }
    };
    return (
        <main className="min-h-screen p-5 pb-28">
            <div className="flex items-center justify-between">
                <h1 className="text-4xl font-bold">
                    {category?.name}
                </h1>

                <button
                    type="button"
                    onClick={() => router.push("/categories")}
                    className="
            flex items-center gap-2
            text-sm font-medium
            text-muted-foreground
            transition-colors
            hover:text-[var(--primary)]
        "
                >
                    <span>Back to categories</span>
                    <span>→</span>
                </button>
            </div>

            <p className="mt-3 text-muted-foreground">
                {category?.description}
            </p>

            {category?.exhibitors.length === 0 ? (
                <div className="mt-20 text-center">
                    <p className="text-lg font-medium">
                        No exhibitors available.
                    </p>
                </div>
            ) : (
                    <div className="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                    {currentExhibitors.map((exhibitor) => (
                        <ExhibitorCard
                            key={exhibitor.id}
                            name={exhibitor.name}
                            description={exhibitor.short_description}
                            photoUrl={exhibitor.photo_url}
                            selected={selectedExhibitor === exhibitor.id}
                            onSelect={() =>
                                setSelectedExhibitor(exhibitor.id)
                            }
                        />
                    ))}
                </div>
            )}
            {totalPages > 1 && (
                <div className="mt-10 flex items-center justify-center gap-4">
                    <button
                        type="button"
                        disabled={currentPage === 1}
                        onClick={() =>
                            setCurrentPage((page) => page - 1)
                        }
                        className="
                rounded-md border px-4 py-2
                disabled:cursor-not-allowed
                disabled:opacity-40
            "
                    >
                        ← Previous
                    </button>

                    <span className="font-medium">
                        {currentPage} / {totalPages}
                    </span>

                    <button
                        type="button"
                        disabled={currentPage === totalPages}
                        onClick={() =>
                            setCurrentPage((page) => page + 1)
                        }
                        className="
                rounded-md border px-4 py-2
                disabled:cursor-not-allowed
                disabled:opacity-40
            "
                    >
                        Next →
                    </button>
                </div>
            )}
            {selected && (
                <div
                    className="
            fixed bottom-0 left-0 right-0 z-50
            border-t bg-white/95
            px-4 py-3
            backdrop-blur
        "
                >
                    <div className="mx-auto flex max-w-5xl items-center gap-4">
                        <div className="min-w-0 flex-1">
                            <p className="text-xs text-muted-foreground">
                                Your selection
                            </p>

                            <p className="truncate font-semibold">
                                {selected.name}
                            </p>
                        </div>

                        <div className="w-40">
                            <VoteAlert
                                disabled={isSubmitting}
                                exhibitorName={selected.name}
                                onConfirm={handleConfirmVote}
                            />
                        </div>
                    </div>
                </div>
            )}
        </main>
    );
}