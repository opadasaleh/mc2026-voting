"use client";

import { useRef, useState } from "react";
import gsap from "gsap";
import Link from "next/link";

export default function CategoriesPage() {
    const [activeCategory, setActiveCategory] = useState(1);

    const panelsRef = useRef<(HTMLElement | null)[]>([]);
    const isAnimating = useRef(false);
    const categories = [
        {
            id: 1,
            name: "Category One",
            color: "var(--chart-1)",
            textColor: "#ffffff",
        },
        {
            id: 2,
            name: "Category Two",
            color: "var(--chart-2)",
            textColor: "var(--foreground)",
        },
        {
            id: 3,
            name: "Category Three",
            color: "var(--chart-4)",
            textColor: "#ffffff",
        },
    ];


    const handleCategoryClick = (id: number) => {


        if (id === activeCategory || isAnimating.current)
            return;

        isAnimating.current = true;
        const clickedPanel = panelsRef.current[id - 1];
        const currentPanel = panelsRef.current[activeCategory - 1];

        if (!clickedPanel || !currentPanel)
            return;

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
    return (
        <main className="h-screen overflow-hidden">
            <div className="flex h-full w-full">
                {categories.map((category) => {
                    const isActive = activeCategory === category.id;

                    return (
                        <section
                            key={category.id}
                            ref={(el) => {
                                panelsRef.current[category.id - 1] = el;
                            }}
                            style={{
                                backgroundColor: category.color,
                                color: category.textColor,
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

                                <div className="mt-auto">
                                    <h2 className="text-4xl font-bold">
                                        {category.name}
                                    </h2>

                                    <p className="mt-3 text-sm">
                                        Category description goes here.
                                    </p>

                                    <Link
                                        href={`/categories/${category.id}`}
                                        className="mt-8 flex items-center justify-between"
                                    >
                                        <span>Explore</span>
                                        <span>→</span>
                                    </Link>
                                </div>
                            </div>
                        </section>
                    );
                })}
            </div>
        </main>
    );
}