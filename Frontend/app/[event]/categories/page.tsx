"use client";

import { useEffect, useRef, useState } from "react";
import gsap from "gsap";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { messageFor, redirectForError } from "@/lib/api/errors";
import { getVisitorToken } from "@/lib/api/session";
import { getCategories, getMe } from "@/lib/api/voting";
import type { Category } from "@/types/api";

// Panel colours by position (an event has at most 3 categories).
const PALETTE = [
    { color: "var(--chart-1)", textColor: "#ffffff" },
    { color: "var(--chart-2)", textColor: "var(--foreground)" },
    { color: "var(--chart-4)", textColor: "#ffffff" },
];

export default function CategoriesPage() {
    const { event } = useParams<{ event: string }>();
    const router = useRouter();

    // Index into `categories`, not a category id: ids are not 1..3 in every event.
    const [activeCategory, setActiveCategory] = useState(0);

    const panelsRef = useRef<(HTMLElement | null)[]>([]);
    const isAnimating = useRef(false);

    const [votedCategories, setVotedCategories] = useState<number[]>([]);

    const [categories, setCategories] = useState<Category[]>([]);

    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState("");


    useEffect(() => {
        const token = getVisitorToken(event);
        if (!token) return;

        // GET /me is the source of truth for "already voted" (F3).
        Promise.all([getCategories(event), getMe(event, token)])
            .then(([loadedCategories, me]) => {
                const voted = me.votes.map((vote) => vote.category_id);
                setCategories(loadedCategories);
                setVotedCategories(voted);
                // Open the first category still to vote in.
                const firstOpen = loadedCategories.findIndex((category) => !voted.includes(category.id));
                setActiveCategory(firstOpen === -1 ? 0 : firstOpen);
            })
            .catch((err) => {
                if (!redirectForError(err, event, router)) {
                    setError(messageFor(err));
                }
            })
            .finally(() => setIsLoading(false));
    }, [event, router]);



    const handleCategoryClick = (index: number) => {


        if (index === activeCategory || isAnimating.current)
            return;
        isAnimating.current = true;

        const clickedPanel = panelsRef.current[index] ?? null;
        const currentPanel = panelsRef.current[activeCategory] ?? null;

        if (clickedPanel === null || currentPanel === null) {
            isAnimating.current = false;
            return;
        }

        const currentContent =
            currentPanel.querySelector(".category-content");

        const nextContent =
            clickedPanel.querySelector(".category-content");

        const currentLabel =
            currentPanel.querySelector(".category-label");

        const nextLabel =
            clickedPanel.querySelector(".category-label");

        const tl = gsap.timeline({
            onComplete: () => {
                setActiveCategory(index);
                isAnimating.current = false;
            },
        });

        // 1. الـ Category الحالية تسكر
        tl.to(
            currentPanel,
            {
                flexGrow: 0,
                flexBasis: 56,
                duration: 0.8,
                ease: "power3.inOut",
            },
            0
        );

        // 2. الـ Category المضغوط عليها تفتح
        tl.to(
            clickedPanel,
            {
                flexGrow: 1,
                flexBasis: 0,
                duration: 0.8,
                ease: "power3.inOut",
            },
            0
        );

        // 3. الاسم العمودي للقديمة يرجع
        tl.to(
            currentLabel,
            {
                x: 0,
                duration: 0.5,
                ease: "power3.inOut",
            },
            0.25
        );

        // 4. الاسم العمودي للجديدة يدخل وراء الـpanel
        tl.to(
            nextLabel,
            {
                x: -56,
                duration: 0.5,
                ease: "power3.inOut",
            },
            0
        );

        // 5. محتوى القديمة
        tl.to(
            currentContent,
            {
                opacity: 0,
                duration: 0.25,
            },
            0.35
        );

        // 6. محتوى الجديدة
        tl.to(
            nextContent,
            {
                opacity: 1,
                duration: 0.35,
                ease: "power2.out",
            },
            0.35
        );
    };


    if (isLoading) {
        return (
            <main className="flex h-screen items-center justify-center">
                <p className="text-lg font-medium">
                    Loading categories...
                </p>
            </main>
        );
    }

    if (error) {
        return (
            <main className="flex h-screen items-center justify-center p-6">
                <p className="text-center text-red-500">
                    {error}
                </p>
            </main>
        );
    }

    if (categories.length === 0) {
        return (
            <main className="flex h-screen items-center justify-center p-6">
                <p className="text-center text-lg font-medium">
                    No categories available.
                </p>
            </main>
        );
    }

    return (
        <main className="h-screen overflow-hidden">

            <div className="flex h-full w-full">
                {categories.map((category, index) => {
                    const isActive = activeCategory === index;
                    const hasVoted = votedCategories.includes(category.id);
                    const palette = PALETTE[index % PALETTE.length];

                    return (
                        <section
                            key={category.id}
                            ref={(el) => {
                                panelsRef.current[index] = el;
                            }}
                            style={{
                                backgroundColor: hasVoted
                                    ? "var(--success)"
                                    : palette.color,

                                color: hasVoted
                                    ? "#ffffff"
                                    : palette.textColor,
                            }}
                            className={`
                                relative h-full overflow-hidden border-r border-white/30
                                ${isActive ? "grow basis-0" : "grow-0 basis-14"}
                            `}
                        >
                            {/* الاسم العمودي - موجود دائماً */}
                            <button
                                type="button"
                                onClick={() => handleCategoryClick(index)}
                                className="
        category-label
        absolute inset-y-0 left-0 z-50
        flex w-14 items-center justify-center
        touch-manipulation
    "
                                style={{
                                    transform: isActive
                                        ? "translateX(-56px)"
                                        : "translateX(0)",
                                }}
                            >
                                <h2 className="[writing-mode:vertical-rl] rotate-180 whitespace-nowrap">
                                    {category.name}
                                </h2>
                            </button>


                            {/* محتوى الـ Category */}
                            <div
                                className="category-content flex h-full flex-col p-6"
                                style={{
                                    opacity: isActive ? 1 : 0,
                                    pointerEvents: isActive ? "auto" : "none",
                                }}
                            >
                                <span className="text-sm">
                                    {String(index + 1).padStart(2, "0")}
                                </span>

                                <div className="mt-auto pb-80">
                                    <h2 className="text-4xl font-bold">
                                        {category.name}
                                    </h2>

                                    <p className="mt-3 text-sm">
                                        {category.description}
                                    </p>


                                    {hasVoted ? (
                                        <div className="mt-8 flex items-center justify-between">
                                            <span>Voted</span>
                                            <span>✓</span>
                                        </div>
                                    ) : (
                                        <Link
                                            href={`/${event}/categories/${category.id}`}
                                            className="
        group mt-8 flex items-center justify-between
        border-b-2 border-current
        pb-2 font-semibold
        transition-opacity
        hover:opacity-70
        active:opacity-50
    "
                                        >
                                            <span>Explore</span>

                                            <span className="transition-transform group-hover:translate-x-1">
                                                →
                                            </span>
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </section>
                    );
                })}
            </div>
        </main>
    );
}