import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";

export default function Loading() {
  return <DataTableSkeleton columns={11} actions={2} />;
}
