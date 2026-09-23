"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useRouter } from "next/navigation"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Upload, Eye, Pencil, Trash2 } from "lucide-react"
import {
  productsItemsDestroy,
  productsItemsIndex,
} from "@/lib/api/product/product"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
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
import { unwrap, withAuth } from "@/lib/api-client"
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
  const tc = useTranslations("products.columns")
  const tt = useTranslations("products.table")

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("refCode")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.accessor("nameKm", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("nameKm")} />
      ),
      cell: ({ row }) => row.getValue("nameKm") ?? "—",
    }),
    columnHelper.accessor("purchasePrice", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("price")} />
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
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <span className={row.getValue("isActive") ? "text-green-600" : "text-muted-foreground"}>
          {row.getValue("isActive") ? tt("active") : tt("inactive")}
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
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem
                  onClick={() => router.push(`/products/${product.id}`)}
                >
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
                <DropdownMenuItem
                  onClick={() => router.push(`/products/${product.id}/edit`)}
                >
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(product)}
                >
                  <Trash2 className="mr-2 size-4" />
                  {tt("delete")}
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
  const t = useTranslations("products.products")
  const tt = useTranslations("products.table")
  const qc = useQueryClient()
  const [importOpen, setImportOpen] = useState(false)
  const [statusFilter, setStatusFilter] = useState("all")
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null)

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => unwrap(await productsItemsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["products"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useProductColumns({
    onDeleteRequest: setDeleteTarget,
  })

  const query = useQuery({
    queryKey: ["products"],
    queryFn: async () =>
      unwrap<Product[]>(
        await productsItemsIndex({}, withAuth())
      ),
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={6} actions={2} />
  }

  return (
    <div>
      <DataTable
        columns={columns}
        data={query.data ?? []}
        searchColumn="name"
        searchPlaceholder={tt("searchPlaceholder")}
        filterColumn="isActive"
        filterValue={statusFilter}
        onFilterChange={setStatusFilter}
        filterOptions={[
          { label: tt("active"), value: "true" },
          { label: tt("inactive"), value: "false" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        toolbar={
          <>
            <Button variant="outline" onClick={() => setImportOpen(true)}>
              <Upload className="mr-2 size-4" />
              {t("import")}
            </Button>
            <Button onClick={() => router.push("/products/create")}>
              <Plus className="mr-2 size-4" />
              {t("newTitle")}
            </Button>
          </>
        }
      />

      <ImportDialog open={importOpen} onOpenChange={setImportOpen} />

      <Dialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>
              {tt.rich("deleteDescription", {
                name: deleteTarget?.name ?? "",
                strong: (chunks) => <strong>{chunks}</strong>,
              })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={deleteMutation.isPending}
            >
              {tt("cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? tt("deleting") : tt("confirmDelete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
