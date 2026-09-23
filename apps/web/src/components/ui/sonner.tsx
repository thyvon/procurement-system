"use client";

import { Toaster as Sonner } from "sonner";

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
  return (
    <Sonner
      theme="light"
      className="toaster group"
      // Sonner injects its own [data-sonner-toaster] font-family (Latin-only)
      // at runtime after our CSS; an inline style on each toast wins over it
      // and lets Khmer text fall back to Battambang like the rest of the app.
      style={{
        fontFamily:
          "var(--font-inter), var(--font-battambang), ui-sans-serif, system-ui, sans-serif",
      }}
      toastOptions={{
        classNames: {
          toast:
            "group toast group-[.toaster]:border-l-4 group-[.toaster]:shadow-xl group-[.toaster]:rounded-xl",
          success:
            "group-[.toaster]:border-l-emerald-500 group-[.toaster]:bg-emerald-50 group-[.toaster]:text-emerald-900 dark:group-[.toaster]:bg-emerald-950/30 dark:group-[.toaster]:text-emerald-100",
          error:
            "group-[.toaster]:border-l-red-500 group-[.toaster]:bg-red-50 group-[.toaster]:text-red-900 dark:group-[.toaster]:bg-red-950/30 dark:group-[.toaster]:text-red-100",
          warning:
            "group-[.toaster]:border-l-amber-500 group-[.toaster]:bg-amber-50 group-[.toaster]:text-amber-900 dark:group-[.toaster]:bg-amber-950/30 dark:group-[.toaster]:text-amber-100",
          info:
            "group-[.toaster]:border-l-blue-500 group-[.toaster]:bg-blue-50 group-[.toaster]:text-blue-900 dark:group-[.toaster]:bg-blue-950/30 dark:group-[.toaster]:text-blue-100",
          description: "group-[.toast]:text-muted-foreground",
          actionButton:
            "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground",
          cancelButton:
            "group-[.toast]:bg-muted group-[.toast]:text-muted-foreground",
        },
      }}
      {...props}
    />
  );
};

export { Toaster };
