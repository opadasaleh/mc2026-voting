"use client";

import { usePathname } from "next/navigation";
import Footer from "./Footer";

// No footer on the full-screen pages: /{event}/categories and /{event}/otp.
const HIDDEN = /^\/[^/]+\/(categories|otp)\/?$/;

export default function FooterWrapper() {
    const pathname = usePathname();

    if (HIDDEN.test(pathname)) {
        return null;
    }

    return <Footer />;
}
