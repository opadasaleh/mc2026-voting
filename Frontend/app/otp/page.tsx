"use client";

import Image from "next/image"
import { InputOTPForm } from "@/components/otp/otpInput"

export default function OtpPage() {
    return (
        <main className="flex min-h-screen justify-center px-6 pt-24">
            <div className="w-full max-w-md">

                <Image
                    src="/images/ao.png"
                    alt="The Maker Collective 2026"
                    width={300}
                    height={120}
                    className="mx-auto mb-8"
                />

                <div className="mb-8">
                    <h1 className="text-3xl font-bold">
                        Verify your phone number
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Enter the verification code sent to your phone
                    </p>
                </div>

                <InputOTPForm />
            </div>
        </main>
    )
}