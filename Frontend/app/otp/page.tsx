"use client"

import Image from "next/image"
import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"

import { InputOTPForm } from "@/components/otp/otpInput"

export default function OtpPage() {
    const router = useRouter()
    const [isChecking, setIsChecking] = useState(true)

    useEffect(() => {
        const phone = sessionStorage.getItem("phone")

        if (!phone) {
            router.replace("/login")
            return
        }

        setIsChecking(false)
    }, [router])

    if (isChecking) {
        return null
    }

    return (
        <main className="relative flex justify-center overflow-hidden px-6 pt-14 pb-16">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <div className="absolute left-1/2 top-[-14rem] h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            </div>

            <div className="w-full max-w-md">
                <Image
                    src="/images/ao.png"
                    alt="The Maker Collective 2026"
                    width={260}
                    height={104}
                    priority
                    className="mx-auto mb-10 h-auto w-56"
                />

                <div className="mb-9 text-center">
                    <h1 className="text-3xl font-bold tracking-tight text-primary">
                        Verify your phone
                    </h1>

                    <p className="mx-auto mt-3 max-w-sm text-muted-foreground">
                        Enter the 6-digit verification code
                        sent to your phone.
                    </p>
                </div>

                <InputOTPForm />
            </div>
        </main>
    )
}