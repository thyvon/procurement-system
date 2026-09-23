"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import {
  productsGroupsDestroy,
  productsGroupsIndex,
  productsGroupsStore,
  productsGroupsUpdate,
} from "@/lib/api/product-group/product-group";
import {
  productsUomsDestroy,
  productsUomsIndex,
  productsUomsStore,
  productsUomsUpdate,
} from "@/lib/api/uom/uom";
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
import { Skeleton } from "@/components/ui/skeleton";
import { unwrap, withAuth } from "@/lib/api-client";

type LookupItem = {
  id: string;
  name: string;
  description: string | null;
};

type Kind = "groups" | "uoms";

const api = {
  groups: {
    list: () => productsGroupsIndex(withAuth()),
    create: (d: { name: string; description?: string }) => productsGroupsStore(d, withAuth()),
    update: (id: string, d: { name?: string; description?: string }) =>
      productsGroupsUpdate(id, d, withAuth()),
    remove: (id: string) => productsGroupsDestroy(id, withAuth()),
  },
  uoms: {
    list: () => productsUomsIndex(withAuth()),
    create: (d: { name: string; description?: string }) =>
      productsUomsStore({ name: d.name }, withAuth()),
    update: (id: string, d: { name?: string; description?: string }) =>
      productsUomsUpdate(id, { name: d.name }, withAuth()),
    remove: (id: string) => productsUomsDestroy(id, withAuth()),
  },
};

/**
 * Shared name/description lookup manager used by the Groups and UoMs tabs.
 */
export function LookupTab({ kind }: { kind: Kind }) {
  const qc = useQueryClient();
  const [editing, setEditing] = useState<LookupItem | null>(null);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: "", description: "" });

  const query = useQuery({
    queryKey: [kind],
    queryFn: async () => unwrap<LookupItem[]>(await api[kind].list()),
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: [kind] });

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editing) return unwrap(await api[kind].update(editing.id, form));
      return unwrap(await api[kind].create(form));
    },
    onSuccess: () => {
      setOpen(false);
      setEditing(null);
      setForm({ name: "", description: "" });
      invalidate();
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => unwrap(await api[kind].remove(id)),
    onSuccess: invalidate,
  });

  const items = Array.isArray(query.data) ? query.data : [];

  return (
    <div>
      <div className="mb-4 flex justify-end">
        <Button
          onClick={() => {
            setEditing(null);
            setForm({ name: "", description: "" });
            setOpen(true);
          }}
        >
          New
        </Button>
      </div>

      <div className="rounded-xl border bg-card">
        {query.isPending &&
          Array.from({ length: 6 }).map((_, i) => (
            <div
              key={i}
              className="flex items-center justify-between border-b px-4 py-3 last:border-b-0"
            >
              <div className="space-y-1.5">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-3 w-48" />
              </div>
              <div className="flex gap-2">
                <Skeleton className="h-8 w-14" />
                <Skeleton className="h-8 w-16" />
              </div>
            </div>
          ))}
        {items.length === 0 && !query.isPending && (
          <p className="p-6 text-sm text-muted-foreground">Nothing here yet.</p>
        )}
        {items.map((item) => (
          <div
            key={item.id}
            className="flex items-center justify-between border-b px-4 py-3 last:border-b-0"
          >
            <div>
              <div className="text-sm font-medium">{item.name}</div>
              {item.description && (
                <div className="text-xs text-muted-foreground">{item.description}</div>
              )}
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setEditing(item);
                  setForm({ name: item.name, description: item.description ?? "" });
                  setOpen(true);
                }}
              >
                Edit
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={() => deleteMutation.mutate(item.id)}
              >
                Delete
              </Button>
            </div>
          </div>
        ))}
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{editing ? "Edit" : "New"} entry</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Name</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>Description</Label>
              <Input
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
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



