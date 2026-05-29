import type { Metadata } from "next";
import { AppProviders } from "@/components/providers/app-providers";
import "./globals.css";
import { Roboto_Slab } from "next/font/google";
import { cn } from "@cognify/ui/lib/utils";

const robotoSlab = Roboto_Slab({subsets:['latin'],variable:'--font-serif'});

export const metadata: Metadata = {
  title: "Cognify",
  description: "Enterprise procurement governance workspace",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" className={cn("h-full antialiased dark", "font-serif", robotoSlab.variable)}>
      <body className="flex min-h-full flex-col">
        <AppProviders>{children}</AppProviders>
      </body>
    </html>
  );
}
