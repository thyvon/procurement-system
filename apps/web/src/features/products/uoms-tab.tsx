"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2, X } from "lucide-react"
import {
  productsUomsDestroy,
  productsUomsIndex,
  productsUomsStore,
  productsUomsUpdate,
} from "@/lib/api/uom/uom"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { unwrap, withAuth } from "@/lib/api-client"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type SubUnit = {
  id: string
  name: string
  shortName: string
  conversionFactor: number
}

type Uom = {
  id: string
  code: string
  name: string
  shortName: string
  isActive: boolean
  subUnits?: SubUnit[]
}

type SubUnitFormRow = {
  id?: string
  name: string
  shortName: string
  conversionFactor: string
}

const columnHelper = createColumnHelper<DataTableFeatures, Uom>()

function useUomColumns({
  onEditRequest,
  onDeleteRequest,
}: {
  onEditRequest: (uom: Uom) => void
  onDeleteRequest: (uom: Uom) => void
}): ColumnDef<DataTableFeatures, Uom>[] {
  const tc = useTranslations("products.columns")
  const tt = useTranslations("products.table")
  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("refCode")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("shortName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("shortName")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("shortName")}</span>
      ),
    }),
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.display({
      id: "subUnits",
      enableSorting: false,
      enableHiding: false,
      header: () => tc("subUnits"),
      cell: ({ row }) => {
        const subUnits = row.original.subUnits ?? []
        if (subUnits.length === 0) {
          return <span className="text-muted-foreground">—</span>
        }
        return (
          <span className="text-xs">
            {subUnits
              .map(
                (s) =>
                  `${s.shortName || s.name}${
                    s.conversionFactor ? ` ×${s.conversionFactor}` : ""
                  }`,
              )
              .join(", ")}
          </span>
        )
      },
    }),
    columnHelper.accessor("isActive", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <span className={row.getValue("isActive") ? "text-green-600" : "text-muted-foreground"}>
          {row.getValue("isActive") ? tt("active") : tt("inactive")}
        </span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const uom = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(uom)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(uom)}
                >
                  <Trash2 className="mr-2 size-4" />
                  {tt("delete")}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        )
      },
    }),
  ] as ColumnDef<DataTableFeatures, Uom>[]
}

const EMPTY_SUB_UNIT: SubUnitFormRow = {
  name: "",
  shortName: "",
  conversionFactor: "",
}

const EMPTY_FORM = {
  name: "",
  shortName: "",
  isActive: true,
  subUnits: [] as SubUnitFormRow[],
}

function isSubUnitRowComplete(row: SubUnitFormRow): boolean {
  return (
    row.name.trim().length > 0 &&
    row.shortName.trim().length > 0 &&
    Number(row.conversionFactor) > 0
  )
}

function isSubUnitRowPartial(row: SubUnitFormRow): boolean {
  const anyFilled =
    row.name.trim().length > 0 ||
    row.shortName.trim().length > 0 ||
    row.conversionFactor.trim().length > 0
  return anyFilled && !isSubUnitRowComplete(row)
}

export function UomsTab() {
  const t = useTranslations("products.uoms")
  const tt = useTranslations("products.table")
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Uom | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Uom | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState({ ...EMPTY_FORM })

  const query = useQuery({
    queryKey: ["uoms"],
    queryFn: async () =>
      unwrap<Uom[]>(await productsUomsIndex(withAuth())),
  })

  const hasInvalidSubUnit = form.subUnits.some(isSubUnitRowPartial)
  const canSave = form.name.trim().length > 0 && !hasInvalidSubUnit

  const saveMutation = useMutation({
    mutationFn: async () => {
      const shortName = form.shortName.trim()
      const payload: {
        name: string
        short_name?: string
        is_active?: boolean
        sub_units?: Array<{
          id?: string
          name: string
          short_name: string
          conversion_factor: number
        }>
      } = {
        name: form.name,
        is_active: form.isActive,
        sub_units: form.subUnits.filter(isSubUnitRowComplete).map((row) => ({
          ...(row.id ? { id: row.id } : {}),
          name: row.name.trim(),
          short_name: row.shortName.trim(),
          conversion_factor: Number(row.conversionFactor),
        })),
      }
      if (shortName) {
        payload.short_name = shortName
      }
      if (editing) {
        return unwrap(await productsUomsUpdate(editing.id, payload, withAuth()))
      }
      return unwrap(await productsUomsStore(payload, withAuth()))
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["uoms"] })
      setFormOpen(false)
      toast.success(editing ? t("updated") : t("created"))
      setEditing(null)
      setForm({ ...EMPTY_FORM })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => unwrap(await productsUomsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["uoms"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const updateSubUnitRow = (index: number, patch: Partial<SubUnitFormRow>) => {
    setForm((f) => ({
      ...f,
      subUnits: f.subUnits.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    }))
  }

  const columns = useUomColumns({
    onEditRequest: (uom) => {
      setEditing(uom)
      setForm({
        name: uom.name,
        shortName: uom.shortName,
        isActive: uom.isActive,
        subUnits: (uom.subUnits ?? []).map((s) => ({
          id: s.id,
          name: s.name,
          shortName: s.shortName,
          conversionFactor: String(s.conversionFactor),
        })),
      })
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={6} actions={1} />
  }

  return (
    <div>
      <DataTable
        columns={columns}
        data={query.data ?? []}
        searchColumn="name"
        searchPlaceholder={tt("searchPlaceholder")}
        filterColumn="isActive"
        filterValue={statusFilter}
        onFilterChange={setStatusFilter}
        filterOptions={[
          { label: tt("active"), value: "true" },
          { label: tt("inactive"), value: "false" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        toolbar={
          <Button
            onClick={() => {
              setEditing(null)
              setForm({ ...EMPTY_FORM })
              setFormOpen(true)
            }}
          >
            <Plus className="mr-2 size-4" />
            {t("newTitle")}
          </Button>
        }
      />

      {/* Create / Edit dialog */}
      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editing ? t("editTitle") : t("newTitle")}</DialogTitle>
            <DialogDescription>
              {editing ? t("editDescription") : t("newDescription")}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <Label htmlFor="uom-name" className="w-28 shrink-0 text-right after:content-[':']">{t("name")} *</Label>
              <Input
                id="uom-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder={t("namePlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="uom-short-name" className="w-28 shrink-0 text-right after:content-[':']">{t("shortName")}</Label>
              <Input
                id="uom-short-name"
                value={form.shortName}
                onChange={(e) => setForm((f) => ({ ...f, shortName: e.target.value }))}
                placeholder={t("shortNamePlaceholder")}
                maxLength={16}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="uom-active" className="w-28 shrink-0 text-right after:content-[':']">{t("active")}</Label>
              <Switch
                id="uom-active"
                checked={form.isActive}
                onCheckedChange={(checked) => setForm((f) => ({ ...f, isActive: checked }))}
              />
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label>{t("subUnits")}</Label>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() =>
                    setForm((f) => ({
                      ...f,
                      subUnits: [...f.subUnits, { ...EMPTY_SUB_UNIT }],
                    }))
                  }
                >
                  <Plus className="mr-1 size-3" />
                  {t("addSubUnit")}
                </Button>
              </div>
              {form.subUnits.length === 0 && (
                <p className="text-xs text-muted-foreground">{t("subUnitsEmpty")}</p>
              )}
              {form.subUnits.map((row, index) => (
                <div
                  key={row.id ?? `new-${index}`}
                  className="space-y-2 rounded-md border p-3"
                >
                  <div className="flex items-start gap-2">
                    <div className="flex-1 space-y-1">
                      <Label htmlFor={`sub-unit-name-${index}`} className="text-xs">
                        {t("subUnitName")} *
                      </Label>
                      <Input
                        id={`sub-unit-name-${index}`}
                        value={row.name}
                        onChange={(e) => updateSubUnitRow(index, { name: e.target.value })}
                        placeholder={t("subUnitNamePlaceholder")}
                        className="h-8"
                      />
                    </div>
                    <div className="w-28 space-y-1">
                      <Label htmlFor={`sub-unit-short-${index}`} className="text-xs">
                        {t("subUnitShortName")} *
                      </Label>
                      <Input
                        id={`sub-unit-short-${index}`}
                        value={row.shortName}
                        onChange={(e) => updateSubUnitRow(index, { shortName: e.target.value })}
                        placeholder={t("subUnitShortNamePlaceholder")}
                        maxLength={16}
                        className="h-8"
                      />
                    </div>
                    <div className="w-24 space-y-1">
                      <Label htmlFor={`sub-unit-factor-${index}`} className="text-xs">
                        {t("conversionFactor")} *
                      </Label>
                      <Input
                        id={`sub-unit-factor-${index}`}
                        type="number"
                        min="0"
                        step="any"
                        value={row.conversionFactor}
                        onChange={(e) =>
                          updateSubUnitRow(index, { conversionFactor: e.target.value })
                        }
                        placeholder={t("conversionFactorPlaceholder")}
                        className="h-8"
                      />
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="mt-5 size-8 shrink-0 text-muted-foreground hover:text-destructive"
                      aria-label={t("removeSubUnit")}
                      onClick={() =>
                        setForm((f) => ({
                          ...f,
                          subUnits: f.subUnits.filter((_, i) => i !== index),
                        }))
                      }
                    >
                      <X className="size-4" />
                    </Button>
                  </div>
                  {isSubUnitRowPartial(row) && (
                    <p className="text-xs text-destructive">{t("subUnitIncomplete")}</p>
                  )}
                </div>
              ))}
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setFormOpen(false)}
              disabled={saveMutation.isPending}
            >
              {t("cancel")}
            </Button>
            <Button
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending || !canSave}
            >
              {saveMutation.isPending ? t("saving") : t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirmation dialog */}
      <Dialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>
              {tt.rich("deleteDescription", {
                name: deleteTarget?.name ?? "",
                strong: (chunks) => <strong>{chunks}</strong>,
              })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={deleteMutation.isPending}
            >
              {tt("cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? tt("deleting") : tt("confirmDelete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
