import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"

export default function Loading() {
  return <DataTableSkeleton columns={5} actions={1} />
}
