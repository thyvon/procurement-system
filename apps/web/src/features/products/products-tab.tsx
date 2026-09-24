"use client"

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useEffect, useState } from "react"
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
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client"
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

type ProductsPage = {
  data: Product[]
  meta?: { page: number; perPage: number; total: number }
}

const columnHelper = createColumnHelper<DataTableFeatures, Product>()

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs)
    return () => clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}

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
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [page, setPage] = useState(0)
  const [perPage, setPerPage] = useState(20)
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null)

  const debouncedSearch = useDebouncedValue(search, 300)
  const status = statusFilter === "all" ? undefined : statusFilter

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
    queryKey: ["products", debouncedSearch, status, page, perPage],
    queryFn: async (): Promise<ProductsPage> => {
      const response = await productsItemsIndex(
        {
          search: debouncedSearch || undefined,
          status: status as "active" | "inactive" | undefined,
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      )
      return unwrapWithMeta<Product[]>(response) as ProductsPage
    },
    placeholderData: keepPreviousData,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={6} actions={2} />
  }

  const meta = query.data?.meta ?? { page: page + 1, perPage, total: 0 }

  return (
    <div>
      <DataTable
        columns={columns}
        data={query.data?.data ?? []}
        loading={query.isFetching}
        serverSearch={{
          value: search,
          onChange: (value) => {
            setSearch(value)
            setPage(0)
          },
        }}
        serverPagination={{
          page,
          perPage,
          total: meta.total,
          onPaginationChange: (next) => {
            setPage(next.page)
            setPerPage(next.perPage)
          },
        }}
        filterColumn="status"
        filterValue={statusFilter}
        onFilterChange={(value) => {
          setStatusFilter(value)
          setPage(0)
        }}
        filterOptions={[
          { label: tt("active"), value: "active" },
          { label: tt("inactive"), value: "inactive" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        searchPlaceholder={tt("searchPlaceholder")}
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
