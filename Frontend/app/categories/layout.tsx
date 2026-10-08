"use client"

import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"

export default function CategoriesLayout({
    children,
}: {
    children: React.ReactNode
}) {
    const router = useRouter()
    const [isCheckingAuth, setIsCheckingAuth] = useState(true)

    useEffect(() => {
        const token = sessionStorage.getItem("token")

        if (!token) {
            router.replace("/login")
            return
        }

        setIsCheckingAuth(false)
    }, [router])

    if (isCheckingAuth) {
        return null
    }

    return <>{children}</>
}