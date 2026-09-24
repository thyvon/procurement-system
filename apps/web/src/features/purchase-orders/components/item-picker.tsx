"use client";

import { useEffect, useMemo, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import { Loader2 } from "lucide-react";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { epurchaseItemsIndex } from "@/lib/api/epurchase-item/epurchase-item";
import { unwrapWithMeta, withAuth } from "@/lib/api-client";

export type CatalogItem = {
  code: string;
  description: string;
  category: string;
  subCategory: string;
  uom: string;
  estimatePrice: number | null;
  avgPrice: number | null;
  status: string;
};

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

function selectedLabel(code: string): string {
  return code;
}

function optionLabel(code: string, description: string): string {
  return description ? `${code} — ${description}` : code;
}

interface ItemPickerProps {
  value: string;
  description: string;
  onSelect: (item: CatalogItem | null) => void;
  placeholder: string;
  emptyMessage: string;
  ariaLabel: string;
}

export function ItemPicker({
  value,
  description,
  onSelect,
  placeholder,
  emptyMessage,
  ariaLabel,
}: ItemPickerProps) {
  const tc = useTranslations("common");
  const [open, setOpen] = useState(false);
  const [inputValue, setInputValue] = useState(() => selectedLabel(value));
  const [searchTerm, setSearchTerm] = useState("");
  const [prevValue, setPrevValue] = useState(value);
  const [prevDescription, setPrevDescription] = useState(description);
  const debouncedTerm = useDebouncedValue(searchTerm.trim(), 300);

  if (value !== prevValue || description !== prevDescription) {
    setPrevValue(value);
    setPrevDescription(description);
    setInputValue(selectedLabel(value));
    setSearchTerm("");
  }

  const searchQuery = useQuery({
    queryKey: ["epurchaseItems", "picker", debouncedTerm],
    queryFn: async (): Promise<CatalogItem[]> => {
      const response = await epurchaseItemsIndex(
        {
          search: debouncedTerm || undefined,
          page: 1,
          per_page: 100,
        },
        withAuth()
      );
      const page = unwrapWithMeta<CatalogItem[]>(response) as {
        data: CatalogItem[];
      };
      return page.data;
    },
    enabled: open,
    retry: false,
    placeholderData: keepPreviousData,
  });

  const rows = useMemo(() => searchQuery.data ?? [], [searchQuery.data]);

  const comboData = useMemo(() => {
    if (!value) return rows;
    if (rows.some((row) => row.code === value)) return rows;
    return [
      {
        code: value,
        description,
        category: "",
        subCategory: "",
        uom: "",
        estimatePrice: null,
        avgPrice: null,
        status: "",
      },
      ...rows,
    ];
  }, [rows, value, description]);

  const items = useMemo(
    () =>
      ComboboxNS.createItems(comboData, {
        getValue: (row) => row.code,
        getLabel: (row) => selectedLabel(row.code),
      }),
    [comboData]
  );

  return (
    <Combobox
      items={items}
      value={value || null}
      inputValue={inputValue}
      onInputValueChange={(next) => {
        const text = String(next ?? "");
        setInputValue(text);
        setSearchTerm(text);
      }}
      onValueChange={(next) => {
        const code = typeof next === "string" ? next : "";
        setSearchTerm("");
        if (!code) {
          onSelect(null);
          setInputValue("");
          return;
        }
        const row = comboData.find((candidate) => candidate.code === code);
        onSelect(row ?? null);
        setInputValue(selectedLabel(code));
      }}
      onOpenChange={(nextOpen) => setOpen(nextOpen)}
      filter={null}
      itemToStringLabel={(code) => {
        const row = comboData.find((candidate) => candidate.code === code);
        return row ? selectedLabel(row.code) : code;
      }}
    >
      <ComboboxInput
        placeholder={placeholder}
        className="h-7 min-w-0 border-0 bg-background px-1.5 font-normal text-xs shadow-none [&_input]:font-normal [&_input]:text-xs [&_input]:md:text-xs"
        aria-label={ariaLabel}
      />
      <ComboboxContent className="min-w-80">
        {searchQuery.isFetching && (
          <div
            role="status"
            className="absolute inset-0 z-10 flex items-center justify-center bg-background/60"
          >
            <Loader2 className="size-4 animate-spin text-muted-foreground" />
            <span className="sr-only">{tc("loading")}</span>
          </div>
        )}
        <ComboboxEmpty className="text-xs">{emptyMessage}</ComboboxEmpty>
        <ComboboxList>
          {(row) => (
            <ComboboxItem key={row.code} value={row.code} className="text-xs">
              {optionLabel(row.code, row.description)}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  );
}
