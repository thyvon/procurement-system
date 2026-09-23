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
            "group toast group-[.toaster]:border-l-4 group-[.toaster]:border-l-primary group-[.toaster]:bg-primary group-[.toaster]:text-primary-foreground group-[.toaster]:shadow-xl group-[.toaster]:rounded-xl",
          success:
            "group-[.toaster]:border-l-primary group-[.toaster]:bg-primary group-[.toaster]:text-primary-foreground",
          error:
            "group-[.toaster]:border-l-destructive group-[.toaster]:bg-destructive group-[.toaster]:text-white",
          warning:
            "group-[.toaster]:border-l-primary group-[.toaster]:bg-secondary group-[.toaster]:text-secondary-foreground",
          info:
            "group-[.toaster]:border-l-primary group-[.toaster]:bg-primary group-[.toaster]:text-primary-foreground",
          description: "group-[.toast]:opacity-80",
          actionButton:
            "group-[.toast]:bg-primary-foreground group-[.toast]:text-primary",
          cancelButton:
            "group-[.toast]:bg-primary-foreground/20 group-[.toast]:text-primary-foreground",
        },
      }}
      {...props}
    />
  );
};

export { Toaster };
