"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslations } from "next-intl";
import {
  organizationCampusesImportStore,
  organizationCampusesImportTemplate,
} from "@/lib/api/organization-import/organization-import";
import {
  organizationDepartmentsImportStore,
  organizationDepartmentsImportTemplate,
} from "@/lib/api/organization-import/organization-import";
import type { StoreCampusImportRequestRowsItem } from "@/lib/api/model/storeCampusImportRequestRowsItem";
import type { StoreDepartmentImportRequestRowsItem } from "@/lib/api/model/storeDepartmentImportRequestRowsItem";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { unwrap, withAuth } from "@/lib/api-client";

type ImportKind = "campus" | "department";

type ImportResult = {
  imported: number;
  skipped: number;
  errors: { row: number; message: string }[];
};

async function parseSheet(file: File): Promise<Record<string, unknown>[]> {
  const XLSX = await import("xlsx");
  const buffer = await file.arrayBuffer();
  const sheet = XLSX.read(buffer, { codepage: 65001 });
  return XLSX.utils.sheet_to_json<Record<string, unknown>>(
    sheet.Sheets[sheet.SheetNames[0]],
    { defval: "" },
  );
}

export function ImportDialog({
  kind,
  open,
  onOpenChange,
}: {
  kind: ImportKind;
  open: boolean;
  onOpenChange: (v: boolean) => void;
}) {
  const t = useTranslations("organization.import");
  const qc = useQueryClient();
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<ImportResult | null>(null);

  const isCampus = kind === "campus";

  const parseMutation = useMutation({
    mutationFn: async (file: File) => {
      const raw = await parseSheet(file);
      if (isCampus) {
        const rows: StoreCampusImportRequestRowsItem[] = raw.map((r) => ({
          name: String(r.name ?? ""),
          status: String(r.status ?? ""),
        }));
        return unwrap<ImportResult>(
          await organizationCampusesImportStore({ rows }, withAuth()),
        );
      }
      const rows: StoreDepartmentImportRequestRowsItem[] = raw.map((r) => ({
        name: String(r.name ?? ""),
        divisions: String(r.divisions ?? ""),
        status: String(r.status ?? ""),
      }));
      return unwrap<ImportResult>(
        await organizationDepartmentsImportStore({ rows }, withAuth()),
      );
    },
    onSuccess: (result) => {
      setResult(result);
      qc.invalidateQueries({
        queryKey: [isCampus ? "campuses" : "departments"],
      });
      if (!isCampus) {
        qc.invalidateQueries({ queryKey: ["divisions"] });
      }
    },
  });

  const downloadTemplate = async () => {
    const res = isCampus
      ? await organizationCampusesImportTemplate(withAuth())
      : await organizationDepartmentsImportTemplate(withAuth());
    const blob = new Blob([res.data as BlobPart], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = isCampus
      ? "campuses-import-template.csv"
      : "departments-import-template.csv";
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(v) => {
        onOpenChange(v);
        if (!v) {
          setResult(null);
          setFile(null);
        }
      }}
    >
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{isCampus ? t("campusTitle") : t("departmentTitle")}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4 text-sm">
          <p className="text-muted-foreground">
            {isCampus ? t("campusDescription") : t("departmentDescription")}
          </p>
          <Button variant="outline" onClick={downloadTemplate}>
            {t("downloadTemplate")}
          </Button>
          <Input
            type="file"
            accept=".xlsx,.xls,.csv"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          />
          {result && (
            <div className="rounded-lg border p-3">
              <div>
                {t("imported")}: <strong>{result.imported}</strong> ·{" "}
                {t("skipped")}: <strong>{result.skipped}</strong>
              </div>
              {result.errors.length > 0 && (
                <ul className="mt-2 max-h-32 list-disc overflow-auto pl-5 text-xs text-destructive">
                  {result.errors.map((e) => (
                    <li key={e.row}>
                      {t("row", { row: e.row })}: {e.message}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>
        <DialogFooter>
          <Button
            disabled={!file || parseMutation.isPending}
            onClick={() => {
              if (file) parseMutation.mutate(file);
            }}
          >
            {parseMutation.isPending ? t("importing") : t("import")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
