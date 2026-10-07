"use client"

import { RefreshCwIcon } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
    Field,
    FieldLabel,
} from "@/components/ui/field"
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSeparator,
    InputOTPSlot,
} from "@/components/ui/input-otp"
import { useEffect, useState } from "react"

export function InputOTPForm() {
    const [otp, setOtp] = useState("")
    const [phone, setPhone] = useState("")

    // console.log(otp)

    useEffect(() => {
        const savedPhone = sessionStorage.getItem("phone")

        console.log("savedphone:", savedPhone)
        if (savedPhone) {
            setPhone(savedPhone)
        }
    }, [])


    function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault()

        const data = {
            phone: phone,
            code: otp,
        }

        console.log(data)
    }
    return (

        <div className="w-full max-w-md">
            <Field>
                <div className="flex items-center justify-between">
                    <FieldLabel htmlFor="otp-verification">
                        Verification code
                    </FieldLabel>

                    <Button variant="ghost" size="sm">
                        <RefreshCwIcon />
                        Resend Code
                    </Button>
                </div>

                <form onSubmit={handleSubmit}>

                    <InputOTP
                        maxLength={6}
                        id="otp-verification"
                        value={otp}
                        onChange={(value) => setOtp(value)}
                        required
                        className="w-full"
                    >

                        <InputOTPGroup>
                            <InputOTPSlot index={0} />
                            <InputOTPSlot index={1} />
                            <InputOTPSlot index={2} />
                        </InputOTPGroup>

                        <InputOTPSeparator />

                        <InputOTPGroup>
                            <InputOTPSlot index={3} />
                            <InputOTPSlot index={4} />
                            <InputOTPSlot index={5} />
                        </InputOTPGroup>
                    </InputOTP>

                    <Button type="submit" className="mt-4 w-full">
                        Verify
                    </Button>
                </form>
            </Field>
        </div>
    )
}