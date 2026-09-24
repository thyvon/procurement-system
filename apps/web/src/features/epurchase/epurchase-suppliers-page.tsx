"use client"

import { keepPreviousData, useQuery } from "@tanstack/react-query"
import { useEffect, useState } from "react"
import { useTranslations } from "next-intl"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { epurchaseSuppliersIndex } from "@/lib/api/epurchase-supplier/epurchase-supplier"
import { unwrapWithMeta, withAuth } from "@/lib/api-client"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type EPurchaseSupplier = {
  code: string
  nameEn: string
  nameKhmer: string
  phone: string
  paymentTerm: string
  isOnboard: string
  status: string
}

type EPurchaseSuppliersPage = {
  data: EPurchaseSupplier[]
  meta?: { page: number; perPage: number; total: number }
}

const columnHelper = createColumnHelper<DataTableFeatures, EPurchaseSupplier>()

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs)
    return () => clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}

function useEPurchaseSupplierColumns(): ColumnDef<DataTableFeatures, EPurchaseSupplier>[] {
  const tc = useTranslations("epurchaseSuppliers.columns")
  const tt = useTranslations("epurchaseSuppliers.table")

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("nameEn", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("nameEn")} />
      ),
      cell: ({ row }) => {
        const name: string = row.getValue("nameEn")
        return (
          <span className="whitespace-normal break-words">{name || "—"}</span>
        )
      },
    }),
    columnHelper.accessor("nameKhmer", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("nameKhmer")} />
      ),
      cell: ({ row }) => {
        const name: string = row.getValue("nameKhmer")
        return (
          <span className="whitespace-normal break-words">{name || "—"}</span>
        )
      },
    }),
    columnHelper.accessor("phone", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("phone")} />
      ),
      cell: ({ row }) => row.getValue("phone") || "—",
    }),
    columnHelper.accessor("paymentTerm", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("paymentTerm")} />
      ),
      cell: ({ row }) => row.getValue("paymentTerm") || "—",
    }),
    columnHelper.accessor("isOnboard", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("isOnboard")} />
      ),
      cell: ({ row }) => {
        const onboard = String(row.getValue("isOnboard"))
        const isOnboarded = onboard === "1" || onboard.toLowerCase() === "onboarded"
        return (
          <span className={isOnboarded ? "text-green-600" : "text-red-600"}>
            {isOnboarded ? tt("onboarded") : tt("notOnboarded")}
          </span>
        )
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
  ] as ColumnDef<DataTableFeatures, EPurchaseSupplier>[]
}

export function EPurchaseSuppliersPage() {
  const t = useTranslations("epurchaseSuppliers")
  const [search, setSearch] = useState("")
  const [page, setPage] = useState(0)
  const [perPage, setPerPage] = useState(10)

  const debouncedSearch = useDebouncedValue(search, 300)
  const columns = useEPurchaseSupplierColumns()

  const query = useQuery({
    queryKey: ["epurchaseSuppliers", debouncedSearch, page, perPage],
    queryFn: async (): Promise<EPurchaseSuppliersPage> => {
      const response = await epurchaseSuppliersIndex(
        {
          search: debouncedSearch || undefined,
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      )
      return unwrapWithMeta<EPurchaseSupplier[]>(response) as EPurchaseSuppliersPage
    },
    placeholderData: keepPreviousData,
    retry: false,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={7} actions={0} />
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
