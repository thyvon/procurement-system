"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ClipboardList } from "lucide-react";
import { cn } from "@/lib/utils";

const VISIBLE_MS = 1800;
const EXIT_MS = 300;
const SKIP_KEY = "procurement.skip_welcome_loader";

type Phase = "visible" | "exiting" | "done";

export function skipWelcomeLoader(): void {
  window.sessionStorage.setItem(SKIP_KEY, "1");
}

export function WelcomeLoader() {
  const t = useTranslations("auth");
  const tCommon = useTranslations("common");
  const [phase, setPhase] = useState<Phase>(() =>
    typeof window !== "undefined" &&
    window.sessionStorage.getItem(SKIP_KEY) === "1"
      ? "done"
      : "visible"
  );

  useEffect(() => {
    window.sessionStorage.removeItem(SKIP_KEY);
  }, []);

  useEffect(() => {
    if (phase === "visible") {
      const timer = setTimeout(() => setPhase("exiting"), VISIBLE_MS);
      return () => clearTimeout(timer);
    }
    if (phase === "exiting") {
      const timer = setTimeout(() => setPhase("done"), EXIT_MS);
      return () => clearTimeout(timer);
    }
  }, [phase]);

  if (phase === "done") return null;

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        "fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 bg-background",
        phase === "exiting" &&
          "animate-out fade-out zoom-out-95 duration-300 ease-out"
      )}
    >
      <div className="relative animate-in zoom-in-50 duration-500 fill-mode-both">
        <span className="absolute -inset-2 animate-ping rounded-xl bg-primary/15" />
        <div className="relative flex size-14 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-lg shadow-primary/25">
          <ClipboardList className="size-7" />
        </div>
      </div>

      <div className="flex animate-in flex-col items-center gap-1 fade-in slide-in-from-bottom-4 duration-700 delay-200 fill-mode-both">
        <h1 className="text-2xl font-bold tracking-tight">{t("welcome")}</h1>
        <p className="text-sm text-muted-foreground">Procurement</p>
      </div>

      <div className="flex animate-in flex-col items-center gap-3 fade-in duration-700 delay-500 fill-mode-both">
        <div className="h-1.5 w-56 overflow-hidden rounded-full bg-muted">
          <div className="welcome-progress h-full w-2/5 rounded-full bg-primary" />
        </div>
        <p className="text-xs text-muted-foreground">{tCommon("loading")}</p>
      </div>
    </div>
  );
}
