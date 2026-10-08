"use client"

import { useEffect } from "react"
import { useParams, useRouter } from "next/navigation"
import { getVisitorToken } from "@/lib/api/session"
import { useHydrated } from "@/lib/useHydrated"

export default function CategoriesLayout({
    children,
}: {
    children: React.ReactNode
}) {
    const router = useRouter()
    const { event } = useParams<{ event: string }>()
    const hydrated = useHydrated()
    const hasToken = hydrated && getVisitorToken(event) !== null

    useEffect(() => {
        if (hydrated && !hasToken) {
            router.replace(`/${event}/login`)
        }
    }, [event, hasToken, hydrated, router])

    if (!hasToken) {
        return null
    }

    return <>{children}</>
}
