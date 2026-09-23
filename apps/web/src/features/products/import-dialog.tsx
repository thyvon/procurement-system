"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import {
  productsImportStore,
  productsImportTemplate,
} from "@/lib/api/product-import/product-import";
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

type ImportResult = {
  imported: number;
  skipped: number;
  errors: { row: number; message: string }[];
};

export function ImportDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
  const qc = useQueryClient();
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<ImportResult | null>(null);

  const parseMutation = useMutation({
    mutationFn: async (file: File) => {
      const XLSX = await import("xlsx");
      const buffer = await file.arrayBuffer();
      const sheet = XLSX.read(buffer, { codepage: 65001 });
      const rows = XLSX.utils.sheet_to_json<Record<string, unknown>>(sheet.Sheets[sheet.SheetNames[0]], {
        defval: "",
      });
      return unwrap<ImportResult>(
        await productsImportStore(
          { rows: rows as never },
          withAuth(),
        ),
      );
    },
    onSuccess: (result) => {
      setResult(result);
      qc.invalidateQueries({ queryKey: ["products"] });
    },
  });

  const downloadTemplate = async () => {
    const res = await productsImportTemplate(withAuth());
    const blob = new Blob([res.data as BlobPart], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "products-import-template.csv";
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <Dialog open={open} onOpenChange={(v) => { onOpenChange(v); if (!v) setResult(null); }}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Import products</DialogTitle>
        </DialogHeader>
        <div className="space-y-4 text-sm">
          <p className="text-muted-foreground">
            Upload an .xlsx/.csv file matching the template columns
            (code, name, name_km, category_code, brand, uom_short_name, purchase_price).
          </p>
          <Button variant="outline" onClick={downloadTemplate}>
            Download template
          </Button>
          <Input type="file" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          {result && (
            <div className="rounded-lg border p-3">
              <div>
                Imported: <strong>{result.imported}</strong> · Skipped:{" "}
                <strong>{result.skipped}</strong>
              </div>
              {result.errors.length > 0 && (
                <ul className="mt-2 max-h-32 list-disc overflow-auto pl-5 text-xs text-destructive">
                  {result.errors.map((e) => (
                    <li key={e.row}>
                      Row {e.row}: {e.message}
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
            {parseMutation.isPending ? "Importing…" : "Import"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}



