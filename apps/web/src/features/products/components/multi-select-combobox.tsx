"use client";

import { useMemo, useState } from "react";
import {
  Combobox,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxItem,
  ComboboxList,
  ComboboxValue,
  useComboboxAnchor,
} from "@/components/ui/combobox";

interface ComboboxOption {
  id: string;
  label: string;
  meta?: string;
}

interface MultiSelectComboboxProps {
  options: ComboboxOption[];
  value: string[];
  onValueChange: (ids: string[]) => void;
  placeholder?: string;
  emptyMessage?: string;
  footer?: React.ReactNode;
  disabled?: boolean;
  className?: string;
}

export function MultiSelectCombobox({
  options,
  value,
  onValueChange,
  placeholder = "Search...",
  emptyMessage = "No items found.",
  footer,
  disabled = false,
  className,
}: MultiSelectComboboxProps) {
  const anchor = useComboboxAnchor();
  const [query, setQuery] = useState("");

  const loweredQuery = query.trim().toLowerCase();
  const filtered = useMemo(
    () => options.filter((o) => !loweredQuery || o.label.toLowerCase().includes(loweredQuery)),
    [options, loweredQuery]
  );

  const selected = useMemo(
    () => options.filter((o) => value.includes(o.id)),
    [options, value]
  );

  return (
    <Combobox
      items={filtered}
      multiple
      value={selected}
      onValueChange={(next) => {
        const arr = Array.isArray(next) ? next : next ? [next] : [];
        onValueChange(arr.map((o) => o.id));
        setQuery("");
      }}
      inputValue={query}
      onInputValueChange={(v) => setQuery(String(v ?? ""))}
      filter={null}
      disabled={disabled}
      itemToStringLabel={(o) => o.label}
      itemToStringValue={(o) => o.id}
    >
      <ComboboxChips ref={anchor} className={className}>
        <ComboboxValue>
          {selected.map((o) => (
            <ComboboxChip key={o.id}>{o.label}</ComboboxChip>
          ))}
        </ComboboxValue>
        <ComboboxChipsInput
          placeholder={placeholder}
          onKeyDown={(e) => {
            if (e.key === "Enter") e.preventDefault();
          }}
        />
      </ComboboxChips>
      <ComboboxContent anchor={anchor}>
        <ComboboxEmpty>{emptyMessage}</ComboboxEmpty>
        <ComboboxList>
          {filtered.map((o) => (
            <ComboboxItem key={o.id} value={o}>
              <span className="flex-1 truncate">{o.label}</span>
              {o.meta && (
                <span className="shrink-0 font-mono text-xs text-muted-foreground">
                  {o.meta}
                </span>
              )}
            </ComboboxItem>
          ))}
        </ComboboxList>
        {footer}
      </ComboboxContent>
    </Combobox>
  );
}
