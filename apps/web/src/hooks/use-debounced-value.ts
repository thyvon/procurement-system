"use client";

import { useEffect, useState } from "react";

/** Settles a changing value after a quiet period — debounced amounts keep
 * the approval preview from refetching on every keystroke. */
export function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}
