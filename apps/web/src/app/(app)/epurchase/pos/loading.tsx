import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"

export default function Loading() {
  return <DataTableSkeleton columns={9} actions={1} />
}
