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
import { epurchaseVendorsSearch } from "@/lib/api/epurchase-vendor/epurchase-vendor";
import { unwrap, withAuth } from "@/lib/api-client";

export type VendorSearchRow = {
  id: number;
  code: string;
  nameEn: string;
  nameKhmer: string;
  text: string;
};

const MIN_SEARCH_LENGTH = 3;

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

function selectedLabel(code: string, nameEn: string): string {
  return nameEn || code;
}

function optionLabel(code: string, nameEn: string): string {
  return nameEn ? `${code} — ${nameEn}` : code;
}

interface SupplierPickerProps {
  value: string;
  supplierName: string;
  onSelect: (row: VendorSearchRow | null) => void;
  placeholder: string;
  emptyMessage: string;
  ariaLabel: string;
}

export function SupplierPicker({
  value,
  supplierName,
  onSelect,
  placeholder,
  emptyMessage,
  ariaLabel,
}: SupplierPickerProps) {
  const t = useTranslations("purchaseOrders.form");
  const tc = useTranslations("common");
  const [open, setOpen] = useState(false);
  const [inputValue, setInputValue] = useState(() =>
    value ? selectedLabel(value, supplierName) : ""
  );
  const [searchTerm, setSearchTerm] = useState("");
  const [prevValue, setPrevValue] = useState(value);
  const [prevName, setPrevName] = useState(supplierName);
  const debouncedTerm = useDebouncedValue(searchTerm.trim(), 300);
  const canSearch = debouncedTerm.length >= MIN_SEARCH_LENGTH;

  if (value !== prevValue || supplierName !== prevName) {
    setPrevValue(value);
    setPrevName(supplierName);
    setInputValue(value ? selectedLabel(value, supplierName) : "");
    setSearchTerm("");
  }

  const searchQuery = useQuery({
    queryKey: ["epurchaseVendors", "search", debouncedTerm],
    queryFn: async (): Promise<VendorSearchRow[]> => {
      const response = await epurchaseVendorsSearch(
        { term: debouncedTerm },
        withAuth()
      );
      return unwrap<VendorSearchRow[]>(response);
    },
    enabled: open && canSearch,
    retry: false,
    placeholderData: keepPreviousData,
  });

  const rows = useMemo(() => searchQuery.data ?? [], [searchQuery.data]);

  const comboData = useMemo(() => {
    if (!value) return rows;
    if (rows.some((row) => row.code === value)) return rows;
    return [
      { id: 0, code: value, nameEn: supplierName, nameKhmer: "", text: value },
      ...rows,
    ];
  }, [rows, value, supplierName]);

  const items = useMemo(
    () =>
      ComboboxNS.createItems(comboData, {
        getValue: (row) => row.code,
        getLabel: (row) => selectedLabel(row.code, row.nameEn),
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
          return;
        }
        const row = comboData.find((candidate) => candidate.code === code);
        onSelect(row ?? null);
      }}
      onOpenChange={(nextOpen) => setOpen(nextOpen)}
      filter={null}
      itemToStringLabel={(code) => {
        const row = comboData.find((candidate) => candidate.code === code);
        return row ? selectedLabel(row.code, row.nameEn) : code;
      }}
    >
      <ComboboxInput
        placeholder={placeholder}
        className="h-7 min-w-0 border-0 bg-background px-1.5 font-normal text-xs shadow-none [&_input]:font-normal [&_input]:text-xs [&_input]:md:text-xs"
        aria-label={ariaLabel}
      />
      <ComboboxContent>
        {searchQuery.isFetching && (
          <div
            role="status"
            className="absolute inset-0 z-10 flex items-center justify-center bg-background/60"
          >
            <Loader2 className="size-4 animate-spin text-muted-foreground" />
            <span className="sr-only">{tc("loading")}</span>
          </div>
        )}
        <ComboboxEmpty className="text-xs">
          {canSearch ? emptyMessage : t("supplierSearchMinChars", { n: MIN_SEARCH_LENGTH })}
        </ComboboxEmpty>
        <ComboboxList>
          {(row) => (
            <ComboboxItem key={row.code} value={row.code} className="text-xs">
              {optionLabel(row.code, row.nameEn)}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  );
}
