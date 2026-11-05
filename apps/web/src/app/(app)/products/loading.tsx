import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"

export default function Loading() {
  return <DataTableSkeleton columns={6} actions={2} />
}
