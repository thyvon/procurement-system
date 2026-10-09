"use client"

import { keepPreviousData, useQuery } from "@tanstack/react-query"
import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"
import { useTranslations } from "next-intl"
import { Eye, MoreHorizontal } from "lucide-react"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { epurchasePosIndex } from "@/lib/api/epurchase-po/epurchase-po"
import { unwrapWithMeta, withAuth } from "@/lib/api-client"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import { type DataTableFeatures } from "@/components/ui/data-table-features"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  EPurchaseLoginRequired,
  requiresEPurchaseLogin,
} from "@/features/epurchase/components/login-required"

type EPurchasePo = {
  id: number
  no: number
  poRefNum: string
  vendorName: string
  purpose: string
  amount: number | null
  currency: string
  prepareByName: string
  createdAt: string
  status: string | null
  purchaseStatus: string | null
}

type EPurchasePosPage = {
  data: EPurchasePo[]
  meta?: { page: number; perPage: number; total: number }
}

const columnHelper = createColumnHelper<DataTableFeatures, EPurchasePo>()

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs)
    return () => clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}

function useEPurchaseColumns(): ColumnDef<DataTableFeatures, EPurchasePo>[] {
  const tc = useTranslations("epurchasePos.columns")
  const tt = useTranslations("epurchasePos.table")
  const router = useRouter()

  return [
    columnHelper.accessor("poRefNum", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("poRefNum")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("poRefNum")}</span>
      ),
    }),
    columnHelper.accessor("vendorName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("vendorName")} />
      ),
      cell: ({ row }) => (
        <span className="whitespace-normal break-words">
          {row.getValue("vendorName")}
        </span>
      ),
    }),
    columnHelper.accessor("purpose", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("purpose")} />
      ),
      cell: ({ row }) => {
        const purpose: string = row.getValue("purpose")
        return (
          <span className="whitespace-normal break-words">{purpose}</span>
        )
      },
    }),
    columnHelper.accessor("amount", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("amount")} />
      ),
      cell: ({ row }) => {
        const amount = row.getValue("amount")
        if (amount === null || amount === undefined) return "—"
        const currency: string = row.original.currency
        return `${currency ? `${currency} ` : ""}${Number(amount).toFixed(2)}`
      },
    }),
    columnHelper.accessor("prepareByName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("prepareByName")} />
      ),
      cell: ({ row }) => row.getValue("prepareByName") || "—",
    }),
    columnHelper.accessor("createdAt", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("createdAt")} />
      ),
      cell: ({ row }) => (
        <span className="whitespace-nowrap text-muted-foreground">
          {row.getValue("createdAt")}
        </span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => row.getValue("status") || "—",
    }),
    columnHelper.accessor("purchaseStatus", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("purchaseStatus")} />
      ),
      cell: ({ row }) => row.getValue("purchaseStatus") || "—",
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => (
        <div className="flex items-center justify-end gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger
              render={<Button variant="ghost" className="size-8 p-0" />}
            >
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem
                  onClick={() =>
                    router.push(
                      `/epurchase/pos/${row.original.id}?ref=${encodeURIComponent(row.original.poRefNum)}`
                    )
                  }
                >
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    }),
  ] as ColumnDef<DataTableFeatures, EPurchasePo>[]
}

export function EPurchasePosPage() {
  const t = useTranslations("epurchasePos")
  const [search, setSearch] = useState("")
  const [page, setPage] = useState(0)
  const [perPage, setPerPage] = useState(20)

  const debouncedSearch = useDebouncedValue(search, 300)
  const columns = useEPurchaseColumns()

  const query = useQuery({
    queryKey: ["epurchasePos", debouncedSearch, page, perPage],
    queryFn: async (): Promise<EPurchasePosPage> => {
      const response = await epurchasePosIndex(
        {
          search: debouncedSearch || undefined,
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      )
      return unwrapWithMeta<EPurchasePo[]>(response) as EPurchasePosPage
    },
    placeholderData: keepPreviousData,
    retry: false,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={9} actions={1} />
  }

  if (query.isError) {
    if (requiresEPurchaseLogin(query.error)) {
      return <EPurchaseLoginRequired />
    }

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
    <div className="flex min-h-0 flex-1 flex-col">
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
