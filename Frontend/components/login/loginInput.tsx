"use client"
import { Button } from "@/components/ui/button"
import {
    Field,
    FieldDescription,
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
import { useState } from "react"
import { Loader2 } from "lucide-react"
import { useRouter } from "next/navigation"



export function InputFieldgroup() {
    const [isLoading, setIsLoading] = useState(false)
    const router = useRouter()

    async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault()
        const formData = new FormData(event.currentTarget)
        
        setIsLoading(true)

        await new Promise((resolve) => setTimeout(resolve, 2000))

        setIsLoading(false)

    
        const name = formData.get("name")
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

        sessionStorage.setItem("phone", fullPhone)

        router.push("/otp")
    
        console.log(data)
        
        // router.push(`/otp?phone=${encodeURIComponent(fullPhone)}`)
        
        console.log(name)
        console.log(fullPhone)
        console.log("submitted")
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
                                placeholder="7xxxxxxxx"
                                required
                            />
                        </div>
                    </Field>
                    <Field orientation="vertical">
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
