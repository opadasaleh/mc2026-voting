"use client";

import { useEffect, useRef, useState } from "react";
import gsap from "gsap";
import Link from "next/link";


type Category = {
    id: number;
    name: string;
    description: string;
    color: string;
    textColor: string;
};

const mockCategories: Category[] = [
    {
        id: 1,
        name: "Category One",
        description: "Category description goes here.",
        color: "var(--chart-1)",
        textColor: "#ffffff",
    },
    {
        id: 2,
        name: "Category Two",
        description: "Category description goes here.",
        color: "var(--chart-2)",
        textColor: "var(--foreground)",
    },
    {
        id: 3,
        name: "Category Three",
        description: "Category description goes here.",
        color: "var(--chart-4)",
        textColor: "#ffffff",
    },
];

export default function CategoriesPage() {
    const [activeCategory, setActiveCategory] = useState(1);

    const panelsRef = useRef<(HTMLElement | null)[]>([]);
    const isAnimating = useRef(false);

    const [votedCategories, setVotedCategories] = useState<number[]>([]);

    const [categories, setCategories] =
        useState<Category[]>(mockCategories);

    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState("");


    useEffect(() => {
        // TODO: API
        // Replace this mock with:
        // GET /events/{event}/me
        //
        // response.data.votes:
        // [
        //     { category_id: 1, exhibitor_id: 12, voted_at: "..." }
        // ]

        // Temporary mock until API integration
        const storedVotes: number[] = JSON.parse(
            sessionStorage.getItem("votedCategories") || "[]"
        );

        setVotedCategories(storedVotes);
    }, []);



    const handleCategoryClick = (id: number) => {


        if (id === activeCategory || isAnimating.current)
            return;
        isAnimating.current = true;

        const clickedPanel = panelsRef.current[id - 1] ?? null;
        const currentPanel = panelsRef.current[activeCategory - 1] ?? null;

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
                setActiveCategory(id);
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
                {categories.map((category) => {
                    const isActive = activeCategory === category.id;
                    const hasVoted = votedCategories.includes(category.id);

                    return (
                        <section
                            key={category.id}
                            ref={(el) => {
                                panelsRef.current[category.id - 1] = el;
                            }}
                            style={{
                                backgroundColor: hasVoted
                                    ? "var(--success)"
                                    : category.color,

                                color: hasVoted
                                    ? "#ffffff"
                                    : category.textColor,
                            }}
                            className={`
                                relative h-full overflow-hidden border-r border-white/30
                                ${isActive ? "grow basis-0" : "grow-0 basis-14"}
                            `}
                        >
                            {/* الاسم العمودي - موجود دائماً */}
                            <button
                                type="button"
                                onClick={() => handleCategoryClick(category.id)}
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
                                    0{category.id}
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
                                            href={`/categories/${category.id}`}
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