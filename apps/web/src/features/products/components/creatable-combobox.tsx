"use client";

import { useMemo, useState } from "react";
import { Plus } from "lucide-react";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";

interface CreatableComboboxProps {
  id?: string;
  value: string;
  onChange: (value: string) => void | Promise<void>;
  options: string[];
  placeholder?: string;
  disabled?: boolean;
}

const matches = (item: string, query: string) => {
  const q = query.trim().toLowerCase();
  if (!q) return true;
  return item.toLowerCase().includes(q);
};

export function CreatableCombobox({
  id,
  value,
  onChange,
  options,
  placeholder = "Search or type to add...",
  disabled = false,
}: CreatableComboboxProps) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState(value || "");
  const [prevValue, setPrevValue] = useState(value);
  const [extraOptions, setExtraOptions] = useState<string[]>([]);

  if (value !== prevValue) {
    setPrevValue(value);
    setQuery(value || "");
  }

  const allOptions = useMemo(() => {
    const set = new Set<string>();
    [...options, ...extraOptions].forEach((o) => {
      if (o && o.trim()) set.add(o.trim());
    });
    return Array.from(set).sort((a, b) => a.localeCompare(b));
  }, [options, extraOptions]);

  const filtered = useMemo(
    () => allOptions.filter((o) => matches(o, query)),
    [allOptions, query]
  );

  const queryValue = query.trim();
  const canAdd =
    queryValue.length > 0 &&
    !allOptions.some((o) => o.toLowerCase() === queryValue.toLowerCase());

  const commitTyped = () => {
    const qv = queryValue;
    if (!qv || qv === value) return;
    const exists = allOptions.some((o) => o.toLowerCase() === qv.toLowerCase());
    if (!exists) {
      setExtraOptions((prev) => (prev.includes(qv) ? prev : [...prev, qv]));
    }
    void onChange(qv);
  };

  const addNew = () => {
    commitTyped();
    setOpen(false);
  };

  return (
    <Combobox
      value={value}
      onValueChange={(_v) => onChange(_v === null ? "" : String(_v))}
      inputValue={query}
      onInputValueChange={(_v) => setQuery(String(_v ?? ""))}
      items={filtered}
      filter={null}
      open={open}
      onOpenChange={(_open) => {
        setOpen(_open);
        if (!_open) commitTyped();
      }}
      disabled={disabled}
    >
      <ComboboxInput
        id={id}
        placeholder={placeholder}
        disabled={disabled}
        showClear={!!value}
        onKeyDown={(e) => {
          if (e.key === "Enter" && filtered.length === 0 && canAdd) {
            e.preventDefault();
            addNew();
          }
        }}
      />
      <ComboboxContent>
        {filtered.length === 0 && !canAdd && (
          <ComboboxEmpty>Type to add a value.</ComboboxEmpty>
        )}
        <ComboboxList>
          {filtered.length === 0 ? (
            canAdd && <AddRow label={queryValue} onClick={addNew} />
          ) : (
            <>
              {filtered.map((opt) => (
                <ComboboxItem key={opt} value={opt}>
                  <span className="flex-1 truncate">{opt}</span>
                </ComboboxItem>
              ))}
              {canAdd && <AddRow label={queryValue} onClick={addNew} />}
            </>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  );
}

function AddRow({ label, onClick }: { label: string; onClick: () => void }) {
  return (
    <div
      onMouseDown={(e) => e.preventDefault()}
      onClick={onClick}
      className="mt-0.5 flex cursor-pointer items-center gap-1.5 rounded-md border-t border-border px-1.5 py-1.5 text-sm text-foreground hover:bg-accent"
    >
      <Plus className="size-4 shrink-0" />
      <span className="truncate">Add &quot;{label}&quot;</span>
    </div>
  );
}
