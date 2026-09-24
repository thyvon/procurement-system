import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { Skeleton } from "@/components/ui/skeleton"

export default function Loading() {
  return (
    <div className="mt-1 space-y-4">
      <div className="inline-flex h-8 w-fit items-center rounded-lg bg-muted p-[3px]">
        {Array.from({ length: 2 }).map((_, i) => (
          <Skeleton key={i} className="mx-0.5 h-[calc(100%-2px)] w-28 rounded-md" />
        ))}
      </div>
      <DataTableSkeleton />
    </div>
  )
}
