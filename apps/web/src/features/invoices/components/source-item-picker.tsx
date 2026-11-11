"use client";

import { useEffect, useMemo, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Loader2 } from "lucide-react";
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import {
  epurchasePosIndex,
  epurchasePosShow,
} from "@/lib/api/epurchase-po/epurchase-po";
import {
  epurchasePrsIndex,
  epurchasePrsShow,
} from "@/lib/api/epurchase-pr/epurchase-pr";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  EPurchaseLoginRequired,
  requiresEPurchaseLogin,
} from "@/features/epurchase/components/login-required";
import type { EPurchasePo, EPurchasePosPage } from "@/features/epurchase/epurchase-pos-page";
import type { EPurchasePoLine } from "@/features/epurchase/epurchase-pos-detail";
import type { EPurchasePr, EPurchasePrsPage } from "@/features/epurchase/epurchase-prs-page";
import type { EPurchasePrLine } from "@/features/epurchase/epurchase-pr-detail";

/** One line as the invoice form receives it from the picker. */
export type PickedSourceLine = {
  description: string;
  uom: string;
  campus: string;
  division: string;
  department: string;
  location: string;
  purpose: string;
  qty: string;
  unitPrice: string;
  discount: string;
  sourceType: "pr" | "po";
  sourceHeaderId: number;
  sourceLineId: number;
  prRef: string;
  poRef: string | null;
  itemCode: string;
};

type SourceType = "pr" | "po";

type SelectedDoc = { id: number; ref: string; purpose: string };

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

interface SourceItemPickerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onAdd: (lines: PickedSourceLine[]) => void;
}

/**
 * Pro-branch only: browse the ePurchase proxy's PRs/POs, tick a subset of a
 * document's items and hand them to the invoice form as pre-filled lines,
 * each carrying the source snapshot (source_type + header/line id + ref +
 * item code) the API requires at least one of. Pure read of the external
 * system — nothing here writes back to ePurchase.
 */
export function SourceItemPicker({
  open,
  onOpenChange,
  onAdd,
}: SourceItemPickerProps) {
  const t = useTranslations("invoices.form");
  const [sourceType, setSourceType] = useState<SourceType>("pr");
  const [search, setSearch] = useState("");
  const [inputValue, setInputValue] = useState("");
  const [doc, setDoc] = useState<SelectedDoc | null>(null);
  const [selectedIds, setSelectedIds] = useState<ReadonlySet<number>>(new Set());

  const debouncedSearch = useDebouncedValue(search.trim(), 300);

  const handleClose = (next: boolean) => {
    if (!next) {
      setDoc(null);
      setSelectedIds(new Set());
      setSearch("");
      setInputValue("");
    }
    onOpenChange(next);
  };

  // Keys and params mirror the ePurchase list pages exactly (page 1,
  // per_page 20) so the picker shares their cache instead of refetching.
  const prsQuery = useQuery({
    queryKey: ["epurchasePrs", debouncedSearch, 0, 20],
    queryFn: async (): Promise<EPurchasePrsPage> => {
      const response = await epurchasePrsIndex(
        { search: debouncedSearch || undefined, page: 1, per_page: 20 },
        withAuth()
      );
      return unwrapWithMeta<EPurchasePr[]>(response) as EPurchasePrsPage;
    },
    enabled: open && sourceType === "pr",
    placeholderData: keepPreviousData,
    retry: false,
  });
  const posQuery = useQuery({
    queryKey: ["epurchasePos", debouncedSearch, 0, 20],
    queryFn: async (): Promise<EPurchasePosPage> => {
      const response = await epurchasePosIndex(
        { search: debouncedSearch || undefined, page: 1, per_page: 20 },
        withAuth()
      );
      return unwrapWithMeta<EPurchasePo[]>(response) as EPurchasePosPage;
    },
    enabled: open && sourceType === "po",
    placeholderData: keepPreviousData,
    retry: false,
  });

  // Shares the detail pages' key and shape, so an already-opened document
  // loads from cache.
  const prLinesQuery = useQuery({
    queryKey: ["epurchasePrs", "detail", String(doc?.id ?? "")],
    queryFn: async (): Promise<EPurchasePrLine[]> =>
      unwrap<EPurchasePrLine[]>(await epurchasePrsShow(String(doc!.id), withAuth())),
    enabled: open && sourceType === "pr" && doc != null,
    retry: false,
  });
  const poLinesQuery = useQuery({
    queryKey: ["epurchasePos", "detail", String(doc?.id ?? "")],
    queryFn: async (): Promise<EPurchasePoLine[]> =>
      unwrap<EPurchasePoLine[]>(await epurchasePosShow(String(doc!.id), withAuth())),
    enabled: open && sourceType === "po" && doc != null,
    retry: false,
  });

  const listQuery = sourceType === "pr" ? prsQuery : posQuery;
  const linesQuery = sourceType === "pr" ? prLinesQuery : poLinesQuery;
  const docLines: (EPurchasePrLine | EPurchasePoLine)[] =
    sourceType === "pr" ? prLinesQuery.data ?? [] : poLinesQuery.data ?? [];
  const docs = useMemo(
    () =>
      (sourceType === "pr"
        ? prsQuery.data?.data ?? []
        : posQuery.data?.data ?? []) as (EPurchasePr | EPurchasePo)[],
    [sourceType, prsQuery.data, posQuery.data]
  );
  const docOptions = useMemo(
    () =>
      docs.map((d) => ({
        value: String(d.id),
        label: "refNum" in d ? d.refNum : d.poRefNum,
        purpose: d.purpose,
      })),
    [docs]
  );
  const items = useMemo(
    () =>
      ComboboxNS.createItems(docOptions, {
        getValue: (d) => d.value,
        getLabel: (d) => d.label,
      }),
    [docOptions]
  );

  const loginRequired =
    requiresEPurchaseLogin(listQuery.error) ||
    requiresEPurchaseLogin(linesQuery.error);

  const allIds = docLines.map((line) => line.id);
  const allSelected =
    allIds.length > 0 && allIds.every((id) => selectedIds.has(id));

  const toggleOne = (id: number, checked: boolean) =>
    setSelectedIds((current) => {
      const next = new Set(current);
      if (checked) next.add(id);
      else next.delete(id);
      return next;
    });

  const handleAdd = () => {
    if (!doc || selectedIds.size === 0) return;

    const picked: PickedSourceLine[] =
      sourceType === "pr"
        ? (prLinesQuery.data ?? [])
            .filter((line) => selectedIds.has(line.id))
            .map((line) => ({
              description: line.description || line.description3 || line.itemCode,
              uom: line.unitType,
              campus: line.campusCode,
              division: line.divisionCode,
              department: line.departmentCode,
              // PR lines carry no location; the officer fills it in — the
              // API requires it on every line.
              location: "",
              purpose: doc.purpose,
              qty: line.qty != null ? String(line.qty) : "",
              unitPrice: line.unitPrice != null ? String(line.unitPrice) : "",
              discount: "",
              sourceType: "pr",
              sourceHeaderId: doc.id,
              sourceLineId: line.id,
              prRef: doc.ref,
              poRef: null,
              itemCode: line.itemCode,
            }))
        : (poLinesQuery.data ?? [])
            .filter((line) => selectedIds.has(line.id))
            .map((line) => ({
              description:
                line.description || line.poDescription || line.itemCode,
              uom: line.unit,
              campus: line.campusCode,
              division: line.divisionCode,
              department: line.departmentCode,
              location: line.location,
              purpose: doc.purpose,
              qty: line.qty != null ? String(line.qty) : "",
              unitPrice: line.unitCost != null ? String(line.unitCost) : "",
              discount: "",
              sourceType: "po" as const,
              sourceHeaderId: doc.id,
              sourceLineId: line.id,
              prRef: line.prRefNum,
              poRef: doc.ref,
              itemCode: line.itemCode,
            }));

    if (picked.length === 0) return;
    onAdd(picked);
    setSelectedIds(new Set());
    handleClose(false);
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-5xl">
        <DialogHeader>
          <DialogTitle>{t("sourcePickerTitle")}</DialogTitle>
          <DialogDescription>{t("sourcePickerDescription")}</DialogDescription>
        </DialogHeader>

        {loginRequired ? (
          <EPurchaseLoginRequired />
        ) : (
          <div className="min-w-0 space-y-3">
            <div className="grid grid-cols-1 items-center gap-2 sm:grid-cols-[10rem_1fr]">
              <Select
                value={sourceType}
                onValueChange={(value) => {
                  const next = value === "po" ? "po" : "pr";
                  setSourceType(next);
                  setDoc(null);
                  setSelectedIds(new Set());
                  setSearch("");
                }}
                items={[
                  { value: "pr", label: t("sourceTypePr") },
                  { value: "po", label: t("sourceTypePo") },
                ]}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t("sourceTypeLabel")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="pr">{t("sourceTypePr")}</SelectItem>
                  <SelectItem value="po">{t("sourceTypePo")}</SelectItem>
                </SelectContent>
              </Select>

              <Combobox
                items={items}
                value={doc ? String(doc.id) : null}
                inputValue={inputValue}
                onInputValueChange={(next) => {
                  const text = String(next ?? "");
                  setInputValue(text);
                  setSearch(text);
                }}
                onValueChange={(next) => {
                  const text = String(next ?? "");
                  const option = docOptions.find((d) => d.value === text);
                  if (!option) {
                    setDoc(null);
                    setSelectedIds(new Set());
                    setInputValue("");
                    return;
                  }
                  setDoc({
                    id: Number(option.value),
                    ref: option.label,
                    purpose: option.purpose,
                  });
                  setSelectedIds(new Set());
                  setInputValue(option.label);
                  setSearch("");
                }}
                filter={null}
                itemToStringLabel={(value) => {
                  const option = docOptions.find((d) => d.value === value);
                  return option ? option.label : "";
                }}
              >
                <ComboboxInput
                  placeholder={
                    sourceType === "pr"
                      ? t("sourceDocumentPlaceholderPr")
                      : t("sourceDocumentPlaceholderPo")
                  }
                  className="w-full"
                />
                {listQuery.isFetching && (
                  <div
                    role="status"
                    className="absolute inset-0 z-10 flex items-center justify-center bg-background/60"
                  >
                    <Loader2 className="size-4 animate-spin text-muted-foreground" />
                    <span className="sr-only">{t("sourceLoading")}</span>
                  </div>
                )}
                <ComboboxContent className="min-w-80">
                  <ComboboxEmpty>{t("sourceDocumentEmpty")}</ComboboxEmpty>
                  <ComboboxList>
                    {(option) => (
                      <ComboboxItem key={option.value} value={option.value}>
                        <span className="flex min-w-0 flex-col">
                          <span className="font-mono text-xs">{option.label}</span>
                          {option.purpose && (
                            <span className="truncate text-[11px] text-muted-foreground">
                              {option.purpose}
                            </span>
                          )}
                        </span>
                      </ComboboxItem>
                    )}
                  </ComboboxList>
                </ComboboxContent>
              </Combobox>
            </div>

            {listQuery.isError && !loginRequired ? (
              <p className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                {listQuery.error instanceof Error && listQuery.error.message
                  ? listQuery.error.message
                  : t("sourceLoadFailed")}
              </p>
            ) : null}

            {!doc ? (
              <p className="rounded-md border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                {t("sourceSelectDocument")}
              </p>
            ) : linesQuery.isPending ? (
              <p className="rounded-md border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                {t("sourceLoading")}
              </p>
            ) : linesQuery.isError ? (
              <p className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                {linesQuery.error instanceof Error && linesQuery.error.message
                  ? linesQuery.error.message
                  : t("sourceLoadFailed")}
              </p>
            ) : allIds.length === 0 ? (
              <p className="rounded-md border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                {t("sourceNoLines")}
              </p>
            ) : (
              <div className="max-h-72 overflow-auto rounded-md border border-border">
                <table className="w-full text-sm">
                  <thead className="sticky top-0 bg-muted/60">
                    <tr>
                      <th className="w-9 px-2 py-2">
                        <Checkbox
                          checked={allSelected}
                          onCheckedChange={(checked) =>
                            setSelectedIds(checked ? new Set(allIds) : new Set())
                          }
                          aria-label={t("sourceSelectAll")}
                        />
                      </th>
                      <th className="min-w-[110px] px-2 py-2 text-left text-xs font-medium text-muted-foreground">
                        {t("itemCode")}
                      </th>
                      <th className="min-w-[180px] px-2 py-2 text-left text-xs font-medium text-muted-foreground">
                        {t("description")}
                      </th>
                      <th className="w-20 px-2 py-2 text-right text-xs font-medium text-muted-foreground">
                        {t("qty")}
                      </th>
                      <th className="w-28 px-2 py-2 text-right text-xs font-medium text-muted-foreground">
                        {t("unitPrice")}
                      </th>
                      <th className="min-w-[140px] px-2 py-2 text-left text-xs font-medium text-muted-foreground">
                        {t("sourceOrg")}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {docLines.map((line) => (
                      <tr key={line.id} className="border-t border-border">
                        <td className="px-2 py-1.5">
                          <Checkbox
                            checked={selectedIds.has(line.id)}
                            onCheckedChange={(checked) =>
                              toggleOne(line.id, checked)
                            }
                            aria-label={line.itemCode}
                          />
                        </td>
                        <td className="whitespace-nowrap px-2 py-1.5 font-mono text-xs">
                          {line.itemCode || "—"}
                        </td>
                        <td className="px-2 py-1.5">
                          <span className="whitespace-normal break-words text-sm">
                            {line.description || "—"}
                          </span>
                        </td>
                        <td className="px-2 py-1.5 text-right font-mono text-sm tabular-nums">
                          {line.qty ?? "—"}
                        </td>
                        <td className="px-2 py-1.5 text-right font-mono text-sm tabular-nums">
                          {sourceType === "pr"
                            ? (line as EPurchasePrLine).unitPrice ?? "—"
                            : (line as EPurchasePoLine).unitCost ?? "—"}
                        </td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-xs text-muted-foreground">
                          {[line.campusCode, line.divisionCode, line.departmentCode]
                            .filter(Boolean)
                            .join(" · ") || "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => handleClose(false)}
            disabled={linesQuery.isFetching}
          >
            {t("cancel")}
          </Button>
          {!loginRequired ? (
            <Button
              onClick={handleAdd}
              disabled={selectedIds.size === 0 || linesQuery.isFetching}
            >
              {t("addSelected", { count: selectedIds.size })}
            </Button>
          ) : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
