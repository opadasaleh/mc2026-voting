"use client";

import { use, useEffect, useState } from "react";
import { ExhibitorCard } from "@/components/categories/exhibitorsCard";
import { VoteAlert } from "@/components/categories/voteAlert";
import { useRouter } from "next/navigation";
import { ApiError } from "@/lib/api/client";
import { messageFor, redirectForError } from "@/lib/api/errors";
import { getVisitorToken } from "@/lib/api/session";
import { castVote, getCategories, getMe } from "@/lib/api/voting";
import type { Category } from "@/types/api";

type CategoryPageProps = {
    params: Promise<{
        event: string;
        categoryId: string;
    }>;
};

export default function CategoryPage({
    params,
}: CategoryPageProps) {

    const { event, categoryId } = use(params);

    const [selectedExhibitor, setSelectedExhibitor] =
        useState<number | null>(null);

    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState("");

    const [isSubmitting, setIsSubmitting] = useState(false);
    const [voteError, setVoteError] = useState("");
    const router = useRouter();

    const [category, setCategory] = useState<Category | null>(null);

    useEffect(() => {
        const token = getVisitorToken(event);
        if (!token) {
            router.replace(`/${event}/login`);
            return;
        }

        Promise.all([getCategories(event), getMe(event, token)])
            .then(([categories, me]) => {
                const found = categories.find((item) => item.id === Number(categoryId));
                if (!found) {
                    setError("This category is not available.");
                    return;
                }
                // Votes cannot be changed: a category already voted in goes back to the list.
                if (me.votes.some((vote) => vote.category_id === found.id)) {
                    router.replace(`/${event}/categories`);
                    return;
                }
                setCategory(found);
            })
            .catch((err) => {
                if (!redirectForError(err, event, router)) {
                    setError(messageFor(err));
                }
            })
            .finally(() => setIsLoading(false));
    }, [event, categoryId, router]);

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

        const token = getVisitorToken(event);
        if (!token) {
            router.replace(`/${event}/login`);
            return;
        }

        setIsSubmitting(true);
        setVoteError("");

        try {
            // 201 = counted, 200 = this exact vote was already counted (safe retry): both mean done.
            await castVote(event, token, {
                category_id: Number(categoryId),
                exhibitor_id: selectedExhibitor,
            });

            router.push(`/${event}/categories`);
        } catch (err) {
            if (redirectForError(err, event, router)) return;

            if (err instanceof ApiError && err.code === "ALREADY_VOTED") {
                // Voted for someone else in this category (e.g. on another tab): it stands.
                router.push(`/${event}/categories`);
                return;
            }

            setVoteError(
                err instanceof ApiError && err.code === "NETWORK_ERROR"
                    ? "Connection lost. Tap Submit Vote again; you will not be counted twice."
                    : messageFor(err)
            );
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
                    onClick={() => router.push(`/${event}/categories`)}
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
                            description={exhibitor.short_description ?? ""}
                            photoUrl={exhibitor.photo_url ?? "/images/default-user.jpg"}
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

                            {voteError && (
                                <p className="text-xs text-destructive">
                                    {voteError}
                                </p>
                            )}
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