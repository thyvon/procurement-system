"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import {
  productsItemsDestroy,
  productsItemsIndex,
  productsItemsStore,
  productsItemsUpdate,
} from "@/lib/api/product/product";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { unwrap, withAuth } from "./api";
import { ImportDialog } from "./import-dialog";

type Product = {
  id: string;
  code: string;
  name: string;
  nameKm: string | null;
  purchasePrice: number | null;
};

const empty = { code: "", name: "", name_km: "", purchase_price: "" };

export function ProductsTab() {
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [dialog, setDialog] = useState<{ open: boolean; product?: Product }>({ open: false });
  const [form, setForm] = useState(empty);
  const [importOpen, setImportOpen] = useState(false);

  const query = useQuery({
    queryKey: ["products", search],
    queryFn: async () =>
      unwrap<{
        data: Product[];
        meta: { nextCursor: string | null };
      }>(await productsItemsIndex({ search: search || undefined }, withAuth())),
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: ["products"] });

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (dialog.product) {
        return productsItemsUpdate(dialog.product.id, form as never, withAuth());
      }
      return productsItemsStore(form as never, withAuth());
    },
    onSuccess: () => {
      setDialog({ open: false });
      setForm(empty);
      invalidate();
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => productsItemsDestroy(id, withAuth()),
    onSuccess: invalidate,
  });

  return (
    <div>
      <div className="mb-4 flex items-center justify-between gap-2">
        <Input
          placeholder="Search by name or code…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setImportOpen(true)}>
            Import
          </Button>
          <Button
            onClick={() => {
              setForm(empty);
              setDialog({ open: true });
            }}
          >
            New product
          </Button>
        </div>
      </div>

      <div className="rounded-xl border bg-card">
        {query.isPending && <p className="p-6 text-sm text-muted-foreground">Loading…</p>}
        {!query.isPending && (query.data?.data ?? []).length === 0 && (
          <p className="p-6 text-sm text-muted-foreground">No products found.</p>
        )}
        {(query.data?.data ?? []).map((p) => (
          <div key={p.id} className="flex items-center justify-between border-b px-4 py-3 last:border-b-0">
            <div className="min-w-0">
              <div className="truncate text-sm font-medium">{p.name}</div>
              <div className="text-xs text-muted-foreground">
                <span className="font-mono">{p.code}</span>
                {p.nameKm ? ` · ${p.nameKm}` : ""}
                {p.purchasePrice !== null ? ` · $${p.purchasePrice}` : ""}
              </div>
            </div>
            <div className="flex shrink-0 gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setForm({
                    code: p.code,
                    name: p.name,
                    name_km: p.nameKm ?? "",
                    purchase_price: p.purchasePrice?.toString() ?? "",
                  });
                  setDialog({ open: true, product: p });
                }}
              >
                Edit
              </Button>
              <Button variant="destructive" size="sm" onClick={() => deleteMutation.mutate(p.id)}>
                Delete
              </Button>
            </div>
          </div>
        ))}
      </div>

      <ImportDialog open={importOpen} onOpenChange={setImportOpen} />

      <Dialog open={dialog.open} onOpenChange={(v) => setDialog((d) => ({ ...d, open: v }))}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{dialog.product ? "Edit" : "New"} product</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Code</Label>
              <Input value={form.code} onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>Name (English)</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>Name (ខ្មែរ)</Label>
              <Input
                lang="km"
                value={form.name_km}
                onChange={(e) => setForm((f) => ({ ...f, name_km: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>Purchase price</Label>
              <Input
                type="number"
                step="0.0001"
                value={form.purchase_price}
                onChange={(e) => setForm((f) => ({ ...f, purchase_price: e.target.value }))}
              />
            </div>
          </div>
          <DialogFooter>
            <Button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

