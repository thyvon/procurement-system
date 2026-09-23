"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useRouter } from "next/navigation"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Upload, Eye, Pencil, Trash2 } from "lucide-react"
import {
  productsItemsDestroy,
  productsItemsIndex,
} from "@/lib/api/product/product"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { unwrap, withAuth } from "./api"
import { ImportDialog } from "./import-dialog"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type Product = {
  id: string
  code: string
  name: string
  nameKm: string | null
  purchasePrice: number | null
  isActive: boolean
}

const columnHelper = createColumnHelper<DataTableFeatures, Product>()

function useProductColumns({
  onDeleteRequest,
}: {
  onDeleteRequest: (product: Product) => void
}): ColumnDef<DataTableFeatures, Product>[] {
  const router = useRouter()

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Code" />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Name" />
      ),
    }),
    columnHelper.accessor("nameKm", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Name (KH)" />
      ),
      cell: ({ row }) => row.getValue("nameKm") ?? "—",
    }),
    columnHelper.accessor("purchasePrice", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Price" />
      ),
      cell: ({ row }) => {
        const price = row.getValue("purchasePrice")
        return price !== null
          ? `$${Number(price).toFixed(2)}`
          : "—"
      },
    }),
    columnHelper.accessor("isActive", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Status" />
      ),
      cell: ({ row }) => (
        <span className={row.getValue("isActive") ? "text-green-600" : "text-muted-foreground"}>
          {row.getValue("isActive") ? "Active" : "Inactive"}
        </span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const product = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">Open menu</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem
                  onClick={() => router.push(`/products/${product.id}`)}
                >
                  <Eye className="mr-2 size-4" />
                  View
                </DropdownMenuItem>
                <DropdownMenuItem
                  onClick={() => router.push(`/products/${product.id}/edit`)}
                >
                  <Pencil className="mr-2 size-4" />
                  Edit
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(product)}
                >
                  <Trash2 className="mr-2 size-4" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        )
      },
    }),
  ] as ColumnDef<DataTableFeatures, Product>[]
}

export function ProductsTab() {
  const router = useRouter()
  const qc = useQueryClient()
  const [importOpen, setImportOpen] = useState(false)
  const [statusFilter, setStatusFilter] = useState("all")
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null)

  const deleteMutation = useMutation({
    mutationFn: (id: string) => productsItemsDestroy(id, withAuth()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["products"] })
      setDeleteTarget(null)
    },
  })

  const columns = useProductColumns({
    onDeleteRequest: setDeleteTarget,
  })

  const query = useQuery({
    queryKey: ["products"],
    queryFn: async () =>
      unwrap<{ data: Product[]; meta: { nextCursor: string | null } }>(
        await productsItemsIndex({}, withAuth())
      ),
  })

  return (
    <div>
      <DataTable
        columns={columns}
        data={query.data?.data ?? []}
        searchColumn="name"
        searchPlaceholder="Filter by name..."
        filterColumn="isActive"
        filterValue={statusFilter}
        onFilterChange={setStatusFilter}
        filterOptions={[
          { label: "Active", value: "true" },
          { label: "Inactive", value: "false" },
        ]}
        filterPlaceholder="All statuses"
        toolbar={
          <>
            <Button variant="outline" onClick={() => setImportOpen(true)}>
              <Upload className="mr-2 size-4" />
              Import
            </Button>
            <Button onClick={() => router.push("/products/create")}>
              <Plus className="mr-2 size-4" />
              New product
            </Button>
          </>
        }
      />

      <ImportDialog open={importOpen} onOpenChange={setImportOpen} />

      <Dialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete product</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete <strong>{deleteTarget?.name}</strong>? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={deleteMutation.isPending}
            >
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? "Deleting..." : "Delete"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
