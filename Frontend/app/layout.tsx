import type { Metadata } from "next";
import localFont from "next/font/local";
import "./globals.css";
import { cn } from "@/lib/utils";
import FooterWrapper from "@/components/footer/FooterWrapper";


const helveticaArabic = localFont({
  src: [
    {
      path: "../fonts/helvetica/HelveticaNeueLT Arabic 45 Light.ttf",
      weight: "400",
      style: "normal",
    },
    {
      path: "../fonts/helvetica/HelveticaNeueLT Arabic 75 Bold.ttf",
      weight: "700",
      style: "normal",
    },
  ],
  variable: "--font-helvetica-arabic",
});

const nexa = localFont({
  src: [
    {
      path: "../fonts/nexa/Nexa-Book.woff2",
      weight: "400",
      style: "normal",
    },
    {
      path: "../fonts/nexa/Nexa-Bold.woff2",
      weight: "700",
      style: "normal",
    },
    {
      path: "../fonts/nexa/Nexa-ExtraBold.woff2",
      weight: "800",
      style: "normal",
    },
  ],
  variable: "--font-nexa",
});

export const metadata: Metadata = {
  title: "MC2026 Voting",
  description: "Vote for your favourite exhibitors at The Maker Collective 2026",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="en"
      className={cn(
        "h-full",
        "antialiased",
        nexa.variable,
        helveticaArabic.variable
      )}    >
      <body className="min-h-screen flex flex-col">
        <main className="flex-1">
          {children}
        </main>

        <FooterWrapper />
      </body>
    </html>
  );
}
