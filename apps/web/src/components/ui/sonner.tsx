"use client";

import { useTheme } from "next-themes";
import { Toaster as Sonner } from "sonner";

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
  const { resolvedTheme } = useTheme();

  return (
    <Sonner
      theme={resolvedTheme === "dark" ? "dark" : "light"}
      className="toaster group"
      // Sonner injects its own [data-sonner-toaster] font-family (Latin-only)
      // at runtime after our CSS; an inline style on each toast wins over it
      // and lets Khmer text fall back to Battambang like the rest of the app.
      style={{
        fontFamily:
          "var(--font-inter), var(--font-battambang), ui-sans-serif, system-ui, sans-serif",
      }}
      toastOptions={{
        // Match sonner default lifetime so the progress bar CSS duration lines up
        duration: 4000,
        classNames: {
          toast:
            "group toast group-[.toaster]:relative group-[.toaster]:overflow-hidden group-[.toaster]:border group-[.toaster]:border-border group-[.toaster]:border-l-4 group-[.toaster]:bg-card group-[.toaster]:text-card-foreground group-[.toaster]:shadow-xl group-[.toaster]:rounded-xl",
          success: "group-[.toaster]:border-l-primary",
          error: "group-[.toaster]:border-l-destructive",
          warning: "group-[.toaster]:border-l-muted-foreground",
          info: "group-[.toaster]:border-l-primary",
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
