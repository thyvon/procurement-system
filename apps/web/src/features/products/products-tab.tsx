"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useRouter } from "next/navigation";
import {
  productsItemsDestroy,
  productsItemsIndex,
} from "@/lib/api/product/product";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { unwrap, withAuth } from "./api";
import { ImportDialog } from "./import-dialog";

type Product = {
  id: string;
  code: string;
  name: string;
  nameKm: string | null;
  purchasePrice: number | null;
};

export function ProductsTab() {
  const qc = useQueryClient();
  const router = useRouter();
  const [search, setSearch] = useState("");
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
          <Button onClick={() => router.push("/products/create")}>
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
                onClick={() => router.push(`/products/${p.id}/edit`)}
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
    </div>
  );
}

