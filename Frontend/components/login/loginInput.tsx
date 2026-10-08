"use client"
import { Button } from "@/components/ui/button"
import {
    Field,
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { Input } from "@/components/ui/input"
import Image from "next/image"
import { useEffect, useState } from "react"
import { Loader2 } from "lucide-react"
import { useParams, useRouter } from "next/navigation"
import { ApiError } from "@/lib/api/client"
import { messageFor, redirectForError } from "@/lib/api/errors"
import { getVisitorToken, savePendingLogin } from "@/lib/api/session"
import { requestOtp } from "@/lib/api/voting"



export function InputFieldgroup() {
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState("")
    const router = useRouter()
    const { event: eventSlug } = useParams<{ event: string }>()

    // Already verified for this event: go straight to voting.
    useEffect(() => {
        if (getVisitorToken(eventSlug)) {
            router.replace(`/${eventSlug}/categories`)
        }
    }, [eventSlug, router])

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault()

        const formData = new FormData(event.currentTarget)

        const name = String(formData.get("name")).trim()
        let phone = String(formData.get("phone"))
        const countryCode = String(formData.get("countryCode"))

        phone = phone.replace(/\D/g, "")

        if (phone.startsWith("0")) {
            phone = phone.slice(1)
        }

        const fullPhone = `${countryCode}${phone}`

        const data = {
            full_name: name,
            phone: fullPhone,
        }

        setIsLoading(true)
        setError("")

        try {
            const result = await requestOtp(eventSlug, data)

            // Name + phone are needed again on the OTP page to resend a code.
            savePendingLogin(eventSlug, { ...data, resend_after: result.resend_after, requested_at: Date.now() })
            router.push(`/${eventSlug}/otp`)
        } catch (err) {
            if (err instanceof ApiError && err.code === "OTP_RATE_LIMITED") {
                // A code was sent moments ago (e.g. the visitor came back from the OTP page): reuse it.
                savePendingLogin(eventSlug, { ...data, resend_after: err.retryAfter, requested_at: Date.now() })
                router.push(`/${eventSlug}/otp`)
            } else if (!redirectForError(err, eventSlug, router)) {
                setError(messageFor(err))
            }
        } finally {
            setIsLoading(false)
        }
    }

    return (
        <div>
            <Image
                src="/images/ao.png"
                alt="The Maker Collective 2026"
                width={300}
                height={120}
                className="mx-auto mb-8"
            />

            <div className="mb-8">
                <h1 className="text-3xl font-bold">Welcome</h1>

                <p className="mb-2 text-muted-foreground">Enter your details to continue</p>
            </div>
            <form onSubmit={handleSubmit}>

                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="name">Name</FieldLabel>
                        <Input
                            id="name"
                            name="name"
                            placeholder="Obada Saleh"
                            required
                        />
                    </Field>
                    <Field>

                        <FieldLabel htmlFor="phone">Phone</FieldLabel>
                        <div className="flex gap-2">
                            <Select name="countryCode" defaultValue="+962">
                                <SelectTrigger className="w-28">
                                    <SelectValue />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value="+962">🇯🇴 +962</SelectItem>
                                    <SelectItem value="+966">🇸🇦 +966</SelectItem>
                                    <SelectItem value="+971">🇦🇪 +971</SelectItem>
                                </SelectContent>
                            </Select>

                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                inputMode="numeric"
                                placeholder="7xxxxxxxx"
                                required
                                onInput={(e) => {
                                    e.currentTarget.value = e.currentTarget.value.replace(/\D/g, "")
                                }}
                            />
                        </div>
                    </Field>
                    <Field orientation="vertical">
                        {error && (
                            <p className="text-sm text-destructive">
                                {error}
                            </p>
                        )}
                        <Button type="submit" disabled={isLoading}>
                            {isLoading ? (
                                <>
                                    <Loader2 className="animate-spin" />
                                    Loading...
                                </>
                            ) : (
                                "Continue"
                            )}
                        </Button>                    </Field>
                </FieldGroup>
            </form>
        </div>
    )
}
