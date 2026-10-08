"use client"

import { useEffect, useState } from "react"
import { useParams, useRouter } from "next/navigation"
import { Loader2 } from "lucide-react"
import { messageFor } from "@/lib/api/errors"
import { getVisitorToken } from "@/lib/api/session"
import { checkAccess } from "@/lib/api/voting"

// QR code entry point: off the venue Wi-Fi → "No access"; already verified → categories; otherwise → login.
export default function EventEntryPage() {
    const { event } = useParams<{ event: string }>()
    const router = useRouter()
    const [error, setError] = useState("")

    useEffect(() => {
        checkAccess(event)
            .then((access) => {
                if (!access.on_site) {
                    router.replace(`/${event}/no-access`)
                } else if (getVisitorToken(event)) {
                    router.replace(`/${event}/categories`)
                } else {
                    router.replace(`/${event}/login`)
                }
            })
            .catch((err) => setError(messageFor(err)))
    }, [event, router])

    return (
        <main className="flex min-h-[60vh] items-center justify-center p-6">
            {error ? (
                <p className="text-center text-destructive">{error}</p>
            ) : (
                <Loader2 className="animate-spin text-primary" aria-label="Loading" />
            )}
        </main>
    )
}
