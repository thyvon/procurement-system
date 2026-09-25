import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"

export default function Loading() {
  return (
    <div className="mt-1 space-y-4">
      <DataTableSkeleton columns={6} actions={2} />
    </div>
  )
}
