import type { Metadata } from "next";
import { Geist_Mono, Inter, Battambang, Caveat } from "next/font/google";
import { getLocale, getMessages } from "next-intl/server";
import { ThemeProvider } from "@/components/layout/theme-provider";
import Providers from "./providers";
import "./globals.css";

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const battambang = Battambang({
  variable: "--font-battambang",
  subsets: ["khmer"],
  weight: ["300", "400", "700", "900"],
});

const caveat = Caveat({
  variable: "--font-caveat",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "Procurement",
  description: "Procurement Management System",
};

export default async function RootLayout({
  children,
}: LayoutProps<"/">) {
  const locale = await getLocale();
  const messages = await getMessages();

  return (
    <html
      lang={locale}
      suppressHydrationWarning
      className={`${inter.variable} ${geistMono.variable} ${battambang.variable} ${caveat.variable} h-full antialiased`}
    >
      <body className="font-sans min-h-full flex flex-col">
        <ThemeProvider>
          <Providers locale={locale} messages={messages}>
            {children}
          </Providers>
        </ThemeProvider>
      </body>
    </html>
  );
}
