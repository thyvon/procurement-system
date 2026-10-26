"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal, Pencil } from "lucide-react";
import { usersUsersIndex } from "@/lib/api/user/user";
import type { UserResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
import { useMe } from "@/hooks/use-me";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/ui/data-table";
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { type DataTableFeatures } from "@/components/ui/data-table-features";
import { EmployeeProfileDialog } from "./employee-profile-dialog";

const columnHelper = createColumnHelper<DataTableFeatures, UserResource>();

const initialsOf = (name: string) =>
  name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

function useEmployeeColumns({
  canManage,
  onManageRequest,
}: {
  canManage: boolean;
  onManageRequest: (user: UserResource) => void;
}): ColumnDef<DataTableFeatures, UserResource>[] {
  const t = useTranslations("users.employeeProfiles");
  const tc = useTranslations("users.employeeProfiles.columns");
  return [
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("employee")} />
      ),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <Avatar className="size-8">
            {row.original.avatar && (
              <AvatarImage src={row.original.avatar} alt="" />
            )}
            <AvatarFallback className="text-xs">
              {initialsOf(row.original.name)}
            </AvatarFallback>
          </Avatar>
          <span className="font-medium">{row.original.name}</span>
        </div>
      ),
    }),
    columnHelper.accessor("cardId", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("cardId")} />
      ),
      cell: ({ row }) => row.original.cardId ?? "—",
    }),
    columnHelper.accessor("position", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("position")} />
      ),
      cell: ({ row }) => row.original.position ?? "—",
    }),
    columnHelper.accessor("campusName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("campus")} />
      ),
      cell: ({ row }) => row.original.campusName ?? "—",
    }),
    columnHelper.accessor("divisionName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("division")} />
      ),
      cell: ({ row }) => row.original.divisionName ?? "—",
    }),
    columnHelper.accessor("departmentName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("department")} />
      ),
      cell: ({ row }) => row.original.departmentName ?? "—",
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        if (!canManage) {
          return null;
        }
        return (
          <DropdownMenu>
            <DropdownMenuTrigger
              render={<Button variant="ghost" className="size-8 p-0" />}
            >
              <span className="sr-only">{t("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onManageRequest(row.original)}>
                <Pencil className="mr-2 size-4" />
                {t("manage")}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    }),
  ] as ColumnDef<DataTableFeatures, UserResource>[];
}

export function EmployeeProfilesTab() {
  const t = useTranslations("users.employeeProfiles");
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("users.manage");
  const [profileTarget, setProfileTarget] = useState<{
    id: number;
    name: string;
  } | null>(null);

  const query = useQuery({
    queryKey: ["users"],
    queryFn: async () =>
      unwrap<UserResource[]>(await usersUsersIndex(withAuth())),
  });

  const columns = useEmployeeColumns({
    canManage,
    onManageRequest: (user) =>
      setProfileTarget({ id: user.id, name: user.name }),
  });

  if (query.isPending) {
    return <DataTableSkeleton columns={7} actions={0} />;
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <DataTable
        columns={columns}
        data={query.data ?? []}
        searchColumn="name"
        searchPlaceholder={t("searchPlaceholder")}
      />

      <EmployeeProfileDialog
        userId={profileTarget?.id ?? 0}
        userName={profileTarget?.name ?? ""}
        open={profileTarget !== null}
        onOpenChange={(open) => !open && setProfileTarget(null)}
      />
    </div>
  );
}
