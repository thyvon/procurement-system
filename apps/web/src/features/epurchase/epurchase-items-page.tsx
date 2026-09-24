"use client"

import { keepPreviousData, useQuery } from "@tanstack/react-query"
import { useEffect, useState } from "react"
import { useTranslations } from "next-intl"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { epurchaseItemsIndex } from "@/lib/api/epurchase-item/epurchase-item"
import { unwrapWithMeta, withAuth } from "@/lib/api-client"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type EPurchaseItem = {
  code: string
  description: string
  category: string
  subCategory: string
  uom: string
  estimatePrice: number | null
  avgPrice: number | null
  status: string
}

type EPurchaseItemsPage = {
  data: EPurchaseItem[]
  meta?: { page: number; perPage: number; total: number }
}

const columnHelper = createColumnHelper<DataTableFeatures, EPurchaseItem>()

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs)
    return () => clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}

function useEPurchaseColumns(): ColumnDef<DataTableFeatures, EPurchaseItem>[] {
  const tc = useTranslations("epurchase.columns")
  const tt = useTranslations("epurchase.table")

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("description", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("description")} />
      ),
      cell: ({ row }) => {
        const description: string = row.getValue("description")
        return (
          <span className="whitespace-normal break-words">{description}</span>
        )
      },
    }),
    columnHelper.accessor("category", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("category")} />
      ),
    }),
    columnHelper.accessor("subCategory", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("subCategory")} />
      ),
      cell: ({ row }) => row.getValue("subCategory") || "—",
    }),
    columnHelper.accessor("uom", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("uom")} />
      ),
      cell: ({ row }) => row.getValue("uom") || "—",
    }),
    columnHelper.accessor("estimatePrice", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("estimatePrice")} />
      ),
      cell: ({ row }) => {
        const price = row.getValue("estimatePrice")
        return price !== null && price !== undefined
          ? `$${Number(price).toFixed(2)}`
          : "—"
      },
    }),
    columnHelper.accessor("avgPrice", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("avgPrice")} />
      ),
      cell: ({ row }) => {
        const price = row.getValue("avgPrice")
        return price !== null && price !== undefined
          ? `$${Number(price).toFixed(2)}`
          : "—"
      },
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => {
        const status = String(row.getValue("status"))
        const isActive = status === "1" || status.toLowerCase() === "active"
        return (
          <span className={isActive ? "text-green-600" : "text-red-600"}>
            {isActive ? tt("active") : tt("inactive")}
          </span>
        )
      },
    }),
  ] as ColumnDef<DataTableFeatures, EPurchaseItem>[]
}

export function EPurchaseItemsPage() {
  const t = useTranslations("epurchase")
  const [search, setSearch] = useState("")
  const [page, setPage] = useState(0)
  const [perPage, setPerPage] = useState(10)

  const debouncedSearch = useDebouncedValue(search, 300)
  const columns = useEPurchaseColumns()

  const query = useQuery({
    queryKey: ["epurchaseItems", debouncedSearch, page, perPage],
    queryFn: async (): Promise<EPurchaseItemsPage> => {
      const response = await epurchaseItemsIndex(
        {
          search: debouncedSearch || undefined,
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      )
      return unwrapWithMeta<EPurchaseItem[]>(response) as EPurchaseItemsPage
    },
    placeholderData: keepPreviousData,
    retry: false,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={8} actions={0} />
  }

  if (query.isError) {
    return (
      <div className="mt-1 rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
        {query.error instanceof Error && query.error.message
          ? query.error.message
          : t("loadFailed")}
      </div>
    )
  }

  const meta = query.data?.meta ?? { page: page + 1, perPage, total: 0 }

  return (
    <div className="mt-1 min-w-0">
      <DataTable
        columns={columns}
        data={query.data?.data ?? []}
        loading={query.isFetching}
        searchPlaceholder={t("searchPlaceholder")}
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
      />
    </div>
  )
}
