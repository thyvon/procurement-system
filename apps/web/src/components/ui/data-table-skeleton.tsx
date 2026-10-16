import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface DataTableSkeletonProps {
  columns?: number
  rows?: number
  actions?: number
  showToolbar?: boolean
}

export function DataTableSkeleton({
  columns = 6,
  rows = 8,
  actions = 2,
  showToolbar = true,
}: DataTableSkeletonProps) {
  return (
    <div className="flex min-h-0 min-w-0 flex-1 flex-col space-y-4">
      {showToolbar && (
        <div className="flex shrink-0 items-center gap-2">
          <Skeleton className="h-8 w-full max-w-sm" />
          <Skeleton className="h-8 w-28" />
          {actions > 0 && (
            <div className="ml-auto flex gap-2">
              {Array.from({ length: actions }).map((_, i) => (
                <Skeleton key={i} className="h-8 w-24" />
              ))}
            </div>
          )}
        </div>
      )}
      <div className="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-md border">
        <Table
          className="h-full"
          containerClassName="min-h-0 flex-1 overflow-y-auto"
        >
          <TableHeader className="[&>tr>th]:sticky [&>tr>th]:top-0 [&>tr>th]:z-10 [&>tr>th]:border-b [&>tr>th]:bg-background">
            <TableRow>
              {Array.from({ length: columns }).map((_, i) => (
                <TableHead key={i}>
                  <Skeleton className="h-4 w-16" />
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {Array.from({ length: rows }).map((_, r) => (
              <TableRow key={r}>
                {Array.from({ length: columns }).map((_, c) => (
                  <TableCell key={c}>
                    <Skeleton className="h-4 w-full min-w-12 max-w-28" />
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      <div className="flex shrink-0 items-center justify-between px-2">
        <Skeleton className="h-4 w-40" />
        <div className="flex items-center gap-6">
          <Skeleton className="hidden h-4 w-24 lg:block" />
          <Skeleton className="h-4 w-24" />
          <div className="flex gap-2">
            <Skeleton className="size-8" />
            <Skeleton className="size-8" />
            <Skeleton className="hidden size-8 lg:block" />
            <Skeleton className="hidden size-8 lg:block" />
          </div>
        </div>
      </div>
    </div>
  )
}
