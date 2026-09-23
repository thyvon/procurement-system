"use client";

import { MutationCache, QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState } from "react";
import { NextIntlClientProvider } from "next-intl";
import { toast } from "sonner";
import { Toaster } from "@/components/ui/sonner";

export default function Providers({
  children,
  locale,
  messages,
}: {
  children: React.ReactNode;
  locale: string;
  messages: Record<string, unknown>;
}) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: { retry: 1, refetchOnWindowFocus: false },
        },
        mutationCache: new MutationCache({
          onError: (error, _variables, _context, mutation) => {
            const meta = mutation.options.meta as { silent?: boolean } | undefined;
            if (meta?.silent) return;
            toast.error(error instanceof Error && error.message ? error.message : "Request failed.");
          },
        }),
      }),
  );

  return (
    <NextIntlClientProvider locale={locale} messages={messages}>
      <QueryClientProvider client={queryClient}>
        {children}
        <Toaster position="top-right" />
      </QueryClientProvider>
    </NextIntlClientProvider>
  );
}
