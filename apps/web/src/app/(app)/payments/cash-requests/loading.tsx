import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";

export default function Loading() {
  return (
    <div className="flex min-h-0 min-w-0 flex-1 flex-col">
      <DataTableSkeleton columns={9} actions={2} />
    </div>
  );
}
