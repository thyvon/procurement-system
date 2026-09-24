"use client";

import * as React from "react";
import { usePathname } from "next/navigation";

const SHOW_DELAY_MS = 100;
const FADE_MS = 250;
const MAX_PROGRESS = 90;
const STUCK_TIMEOUT_MS = 8000;

function isModifiedClick(event: MouseEvent) {
  return (
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey ||
    event.button !== 0
  );
}

function getInternalPath(target: EventTarget | null): string | null {
  const anchor = target instanceof Element ? target.closest("a") : null;
  if (!anchor) return null;
  if (anchor.target && anchor.target !== "_self") return null;
  if (anchor.hasAttribute("download")) return null;

  const href = anchor.getAttribute("href");
  if (!href || !href.startsWith("/") || href.startsWith("//")) return null;

  return href;
}

export function RouteProgressBar() {
  const pathname = usePathname();
  const [visible, setVisible] = React.useState(false);
  const [progress, setProgress] = React.useState(0);
  const [fading, setFading] = React.useState(false);

  const loadingRef = React.useRef(false);
  const lastPathRef = React.useRef(pathname);
  const showTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
  const tickRef = React.useRef<ReturnType<typeof setInterval> | null>(null);
  const fadeTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
  const stuckTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);

  const clearAll = React.useCallback(() => {
    if (showTimerRef.current) clearTimeout(showTimerRef.current);
    if (tickRef.current) clearInterval(tickRef.current);
    if (fadeTimerRef.current) clearTimeout(fadeTimerRef.current);
    if (stuckTimerRef.current) clearTimeout(stuckTimerRef.current);
    showTimerRef.current = null;
    tickRef.current = null;
    fadeTimerRef.current = null;
    stuckTimerRef.current = null;
  }, []);

  const start = React.useCallback(() => {
    clearAll();
    loadingRef.current = true;
    setFading(false);
    setProgress(4);

    showTimerRef.current = setTimeout(() => {
      setVisible(true);
      tickRef.current = setInterval(() => {
        setProgress((value) =>
          value >= MAX_PROGRESS
            ? value
            : value + Math.max(1.5, (MAX_PROGRESS - value) * 0.15)
        );
      }, 70);
    }, SHOW_DELAY_MS);

    stuckTimerRef.current = setTimeout(() => {
      loadingRef.current = false;
      clearAll();
      setVisible(false);
      setProgress(0);
      setFading(false);
    }, STUCK_TIMEOUT_MS);
  }, [clearAll]);

  const complete = React.useCallback(() => {
    if (!loadingRef.current) return;
    loadingRef.current = false;
    clearAll();
    setProgress(100);
    setFading(true);
    fadeTimerRef.current = setTimeout(() => {
      setVisible(false);
      fadeTimerRef.current = setTimeout(() => {
        setProgress(0);
        setFading(false);
      }, FADE_MS);
    }, 180);
  }, [clearAll]);

  React.useEffect(() => clearAll, [clearAll]);

  React.useEffect(() => {
    function onClick(event: MouseEvent) {
      if (isModifiedClick(event) || event.defaultPrevented) return;
      const path = getInternalPath(event.target);
      if (!path) return;
      const current = window.location.pathname + window.location.search;
      if (path === current || path === window.location.pathname) return;
      start();
    }

    function onPopState() {
      start();
    }

    document.addEventListener("click", onClick, true);
    window.addEventListener("popstate", onPopState);
    return () => {
      document.removeEventListener("click", onClick, true);
      window.removeEventListener("popstate", onPopState);
    };
  }, [start]);

  React.useEffect(() => {
    if (lastPathRef.current === pathname) return;
    lastPathRef.current = pathname;
    complete();
  }, [pathname, complete]);

  if (!visible) return null;

  return (
    <div
      role="progressbar"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={Math.round(progress)}
      aria-label="Loading"
      className="pointer-events-none fixed inset-x-0 top-0 z-[100] h-1 bg-primary/20"
    >
      <div
        className="h-full bg-primary transition-all duration-200 ease-out"
        style={{
          width: `${Math.min(progress, 100)}%`,
          opacity: fading ? 0 : 1,
        }}
      />
    </div>
  );
}
