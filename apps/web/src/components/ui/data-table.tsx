"use client"

import * as React from "react"
import {
  useTable,
  FlexRender,
  type ColumnDef,
  type ColumnFiltersState,
  type ColumnVisibilityState,
  type PaginationState,
  type RowData,
  type SortingState,
  type TableOptions,
  type Updater,
} from "@tanstack/react-table"
import { useTranslations } from "next-intl"
import { Loader2 } from "lucide-react"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { features, type DataTableFeatures } from "./data-table-features"
import { DataTablePagination } from "./data-table-pagination"
import { DataTableToolbar } from "./data-table-toolbar"

interface FilterOption {
  label: string
  value: string
}

/** Parent-owned pagination (0-based page) for server-side tables. */
export interface DataTableServerPagination {
  page: number
  perPage: number
  total: number
  onPaginationChange: (next: { page: number; perPage: number }) => void
}

/** Parent-owned search value (sent to the API instead of a column filter). */
export interface DataTableServerSearch {
  value: string
  onChange: (value: string) => void
}

interface DataTableProps<TData extends RowData> {
  columns: ColumnDef<DataTableFeatures, TData>[]
  data: TData[]
  searchColumn?: string
  searchPlaceholder?: string
  filterColumn?: string
  filterValue?: string
  onFilterChange?: (value: string) => void
  filterOptions?: FilterOption[]
  filterPlaceholder?: string
  toolbar?: React.ReactNode
  serverPagination?: DataTableServerPagination
  serverSearch?: DataTableServerSearch
  /** In-flight server request (e.g. query.isFetching) — shows a centered spinner over the table. */
  loading?: boolean
}

export function DataTable<TData extends RowData>({
  columns,
  data,
  searchColumn,
  searchPlaceholder,
  filterColumn,
  filterValue,
  onFilterChange,
  filterOptions,
  filterPlaceholder,
  toolbar,
  serverPagination,
  serverSearch,
  loading = false,
}: DataTableProps<TData>) {
  const t = useTranslations("common")
  const [sorting, setSorting] = React.useState<SortingState>([])
  const [columnFilters, setColumnFilters] =
    React.useState<ColumnFiltersState>([])
  const [columnVisibility, setColumnVisibility] =
    React.useState<ColumnVisibilityState>({})
  const [rowSelection, setRowSelection] = React.useState({})

  const options: TableOptions<DataTableFeatures, TData> = {
    features,
    data,
    columns,
    onSortingChange: setSorting,
    onColumnFiltersChange: setColumnFilters,
    onColumnVisibilityChange: setColumnVisibility,
    onRowSelectionChange: setRowSelection,
    state: {
      sorting,
      columnFilters,
      columnVisibility,
      rowSelection,
      ...(serverPagination
        ? {
            pagination: {
              pageIndex: serverPagination.page,
              pageSize: serverPagination.perPage,
            } satisfies PaginationState,
          }
        : {}),
    },
    ...(serverPagination
      ? {
          manualPagination: true,
          rowCount: serverPagination.total,
          autoResetPageIndex: false,
          onPaginationChange: (updater: Updater<PaginationState>) => {
            const next =
              typeof updater === "function"
                ? updater({
                    pageIndex: serverPagination.page,
                    pageSize: serverPagination.perPage,
                  })
                : updater
            serverPagination.onPaginationChange({
              page: next.pageIndex,
              perPage: next.pageSize,
            })
          },
        }
      : {}),
  }

  const table = useTable(options)

  const showToolbar = Boolean(searchColumn || serverSearch)
  const searchValue = serverSearch
    ? serverSearch.value
    : searchColumn
      ? ((table.getColumn(searchColumn)?.getFilterValue() as string) ?? "")
      : ""
  const handleSearchChange = serverSearch
    ? serverSearch.onChange
    : searchColumn
      ? (value: string) => table.getColumn(searchColumn)?.setFilterValue(value)
      : undefined

  return (
    <div className="space-y-4">
      {showToolbar && (
        <DataTableToolbar
          searchValue={searchValue}
          onSearchChange={handleSearchChange}
          searchPlaceholder={searchPlaceholder}
          filterColumn={filterColumn}
          filterValue={filterValue}
          onFilterChange={onFilterChange}
          filterOptions={filterOptions}
          filterPlaceholder={filterPlaceholder}
        >
          {toolbar}
        </DataTableToolbar>
      )}
      <div
        className="relative overflow-hidden rounded-md border"
        aria-busy={loading || undefined}
      >
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <TableHead key={header.id}>
                    {header.isPlaceholder ? null : (
                      <FlexRender header={header} />
                    )}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows?.length ? (
              table.getRowModel().rows.map((row) => (
                <TableRow
                  key={row.id}
                  data-state={row.getIsSelected() && "selected"}
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      <FlexRender cell={cell} />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell
                  colSpan={columns.length}
                  className="h-24 text-center"
                >
                  No results.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
        {loading && (
          <div
            role="status"
            className="absolute inset-0 z-10 flex items-center justify-center bg-background/60"
          >
            <Loader2 className="size-5 animate-spin text-muted-foreground" />
            <span className="sr-only">{t("loading")}</span>
          </div>
        )}
      </div>
      <DataTablePagination table={table} />
    </div>
  )
}
