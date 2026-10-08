"use client"

import { useCallback, useEffect, useState } from "react"
import Image from "next/image"
import { useParams, useRouter } from "next/navigation"
import { Loader2, RefreshCw, WifiOff } from "lucide-react"
import { Button } from "@/components/ui/button"
import { messageFor } from "@/lib/api/errors"
import { checkAccess } from "@/lib/api/voting"

// Shown when the phone is not on the venue Wi-Fi (F11). The API enforces the rule
// on every vote; this page only explains how to get on the right network.
export default function NoAccessPage() {
    const { event } = useParams<{ event: string }>()
    const router = useRouter()
    const [wifiName, setWifiName] = useState<string | null>(null)
    const [isChecking, setIsChecking] = useState(false)
    const [message, setMessage] = useState("")

    const tryAgain = useCallback(async () => {
        setIsChecking(true)
        setMessage("")
        try {
            const access = await checkAccess(event)
            setWifiName(access.venue_wifi_name)
            if (access.on_site) {
                router.replace(`/${event}`)
                return
            }
            setMessage("Still not connected to the event Wi-Fi.")
        } catch (error) {
            setMessage(messageFor(error))
        } finally {
            setIsChecking(false)
        }
    }, [event, router])

    useEffect(() => {
        checkAccess(event)
            .then((access) => setWifiName(access.venue_wifi_name))
            .catch(() => {})
    }, [event])

    return (
        <main className="flex justify-center px-6 pt-14 pb-16">
            <div className="w-full max-w-md text-center">
                <Image
                    src="/images/ao.png"
                    alt="The Maker Collective 2026"
                    width={260}
                    height={104}
                    priority
                    className="mx-auto mb-10 h-auto w-56"
                />

                <WifiOff className="mx-auto mb-6 h-12 w-12 text-primary" aria-hidden />

                <h1 className="text-3xl font-bold tracking-tight text-primary">
                    Join the event Wi-Fi to vote
                </h1>

                <p className="mt-4 text-muted-foreground">
                    Voting is only available at the venue, on the event Wi-Fi.
                </p>

                <ol className="mt-6 space-y-2 rounded-xl border p-4 text-left text-sm">
                    <li>
                        1. Connect to{" "}
                        <strong>{wifiName ?? "the event Wi-Fi"}</strong>
                    </li>
                    <li>2. Turn off mobile data, any VPN and iCloud Private Relay</li>
                    <li>3. Tap Try again</li>
                </ol>

                {message && <p className="mt-4 text-sm text-destructive">{message}</p>}

                <Button className="mt-6 h-12 w-full rounded-xl text-base" onClick={tryAgain} disabled={isChecking}>
                    {isChecking ? <Loader2 className="animate-spin" /> : <RefreshCw />}
                    Try again
                </Button>
            </div>
        </main>
    )
}
