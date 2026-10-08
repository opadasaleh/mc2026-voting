import { redirect } from "next/navigation"

// Each event's QR code points to /{event-slug}; the bare domain opens the default event.
export default function Home() {
    redirect(`/${process.env.NEXT_PUBLIC_DEFAULT_EVENT ?? "mc2026"}`)
}
