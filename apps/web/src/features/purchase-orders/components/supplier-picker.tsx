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
import { epurchaseSuppliersIndex } from "@/lib/api/epurchase-supplier/epurchase-supplier";
import { unwrapWithMeta, withAuth } from "@/lib/api-client";
import { cn } from "@/lib/utils";

export type SupplierRow = {
  id: number;
  code: string;
  name: string;
  phone: string;
  address: string;
  vatPercentage: string;
};

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

function selectedLabel(name: string, code: string): string {
  return name || code;
}

function optionLabel(code: string, name: string): string {
  return name ? `${code} — ${name}` : code;
}

interface SupplierPickerProps {
  value: string;
  supplierName: string;
  onSelect: (row: SupplierRow | null) => void;
  placeholder: string;
  emptyMessage: string;
  ariaLabel: string;
  /** Merged over the compact table-cell default — form rows pass their own sizing. */
  className?: string;
}

export function SupplierPicker({
  value,
  supplierName,
  onSelect,
  placeholder,
  emptyMessage,
  ariaLabel,
  className,
}: SupplierPickerProps) {
  const tc = useTranslations("common");
  const [open, setOpen] = useState(false);
  const [inputValue, setInputValue] = useState(() =>
    value || supplierName ? selectedLabel(supplierName, value) : ""
  );
  const [searchTerm, setSearchTerm] = useState("");
  const [prevValue, setPrevValue] = useState(value);
  const [prevName, setPrevName] = useState(supplierName);
  const debouncedTerm = useDebouncedValue(searchTerm.trim(), 300);

  if (value !== prevValue || supplierName !== prevName) {
    setPrevValue(value);
    setPrevName(supplierName);
    setInputValue(
      value || supplierName ? selectedLabel(supplierName, value) : ""
    );
    setSearchTerm("");
  }

  const searchQuery = useQuery({
    queryKey: ["epurchaseSuppliers", "picker", debouncedTerm],
    queryFn: async (): Promise<SupplierRow[]> => {
      const response = await epurchaseSuppliersIndex(
        {
          search: debouncedTerm || undefined,
          page: 1,
          per_page: 100,
        },
        withAuth()
      );
      const page = unwrapWithMeta<SupplierRow[]>(response) as {
        data: SupplierRow[];
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
        id: 0,
        code: value,
        name: supplierName,
        phone: "",
        address: "",
        vatPercentage: "",
      },
      ...rows,
    ];
  }, [rows, value, supplierName]);

  const items = useMemo(
    () =>
      ComboboxNS.createItems(comboData, {
        getValue: (row) => row.code,
        getLabel: (row) => selectedLabel(row.name, row.code),
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
        // Parent may reject a duplicate; restore the committed selection display.
        setInputValue(value ? selectedLabel(supplierName, value) : "");
      }}
      onOpenChange={(nextOpen) => setOpen(nextOpen)}
      filter={null}
      itemToStringLabel={(code) => {
        const row = comboData.find((candidate) => candidate.code === code);
        return row ? selectedLabel(row.name, row.code) : code;
      }}
    >
      <ComboboxInput
        placeholder={placeholder}
        showClear
        className={cn(
          "h-7 min-w-0 border-0 bg-background px-1.5 font-normal text-xs shadow-none [&_input]:font-normal [&_input]:text-xs [&_input]:md:text-xs",
          className
        )}
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
        <ComboboxEmpty className="text-xs">{emptyMessage}</ComboboxEmpty>
        <ComboboxList>
          {(row) => (
            <ComboboxItem key={row.code} value={row.code} className="text-xs">
              {optionLabel(row.code, row.name)}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  );
}
