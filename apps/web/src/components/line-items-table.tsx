"use client";

import type { ReactNode } from "react";
import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";

export const headCell =
  "border border-border bg-muted/60 px-2 py-1 text-left text-xs font-medium";
export const bodyCell = "border border-border p-1 align-top text-xs";
export const cellInputClass =
  "h-7 rounded-none border-0 bg-background px-1.5 text-xs shadow-none placeholder:text-xs md:text-xs";
export const cellInputRightClass = `${cellInputClass} text-right`;
/** Boxed input for the merged totals footer — it is not a grid cell. */
export const summaryInputClass =
  "h-7 w-28 rounded-none border border-border bg-background px-1.5 text-right text-xs tabular-nums shadow-none placeholder:text-xs md:text-xs";

const ALIGN_CLASS: Record<string, string> = {
  left: "text-left",
  right: "text-right",
  center: "text-center",
};

/**
 * Table min-width budget in px — the same pattern as the evaluation matrix's
 * `tableWidth`: fixed px columns don't shrink on zoom while the viewport
 * does, so without this floor the wrapper stops scrolling, the flexible
 * description column is squeezed, and its header bleeds into the next cell.
 * Below the budget this wrapper scrolls instead.
 */
const FIXED_COLUMNS_WIDTH = 560; // qty 96 + unit 128 + discount 112 + line 112 + USD 112
const DESCRIPTION_MIN_WIDTH = 168;
const INDEX_COLUMN_WIDTH = 32; // showIndex
const ACTIONS_COLUMN_WIDTH = 40; // actions / onRemove

export type LineItemsColumn<T> = {
  key: string;
  label: string;
  width?: string;
  align?: "left" | "right" | "center";
  cell: (row: T, index: number) => ReactNode;
};

export type LineItemsSummary = {
  key: string;
  label: string;
  value: ReactNode;
  strong?: boolean;
};

export interface LineItemsTableProps<T> {
  columns: LineItemsColumn<T>[];
  rows: T[];
  rowKey: (row: T, index: number) => string;
  disabled?: boolean;
  /** Leading row-number column. */
  showIndex?: boolean;
  /** Trailing actions column — created automatically when `onRemove` is set. */
  actionsLabel?: string;
  actions?: (row: T, index: number) => ReactNode;
  onRemove?: (index: number) => void;
  minRows?: number;
  removeLabel?: string;
  summary?: LineItemsSummary[];
  /** Rendered on the left of the totals footer — the usual home for "Add row". */
  footerLeading?: ReactNode;
}

/**
 * Spreadsheet-flavoured editable grid: flush bordered cells, h-7 inputs and a
 * totals footer — the look of the evaluation matrix, reusable for any line
 * editor. Labels arrive as props (no i18n inside); columns are declared by
 * the caller. Optional leading # column and trailing actions column.
 */
export function LineItemsTable<T>({
  columns,
  rows,
  rowKey,
  disabled = false,
  showIndex = false,
  actionsLabel,
  actions,
  onRemove,
  minRows = 1,
  removeLabel,
  summary,
  footerLeading,
}: LineItemsTableProps<T>) {
  const hasActions = Boolean(actions) || Boolean(onRemove);
  const canRemove = !disabled && rows.length > minRows;
  const indexSpan = showIndex ? 1 : 0;
  const totalSpan = indexSpan + columns.length + (hasActions ? 1 : 0);
  const tableWidth =
    FIXED_COLUMNS_WIDTH +
    DESCRIPTION_MIN_WIDTH +
    (showIndex ? INDEX_COLUMN_WIDTH : 0) +
    (hasActions ? ACTIONS_COLUMN_WIDTH : 0);

  return (
    <div className="w-full overflow-x-auto border border-border">
      <table
        className="w-full border-collapse text-xs table-fixed"
        style={{ width: "100%", minWidth: tableWidth }}
      >
        <colgroup>
          {showIndex ? <col className="w-8" /> : null}
          {columns.map((column) => (
            <col key={column.key} className={column.width} />
          ))}
          {hasActions ? <col className="w-10" /> : null}
        </colgroup>
        <thead>
          <tr>
            {showIndex ? <th className={headCell} /> : null}
            {columns.map((column) => (
              <th
                key={column.key}
                className={`${headCell} ${ALIGN_CLASS[column.align ?? "left"]}`}
              >
                {column.label}
              </th>
            ))}
            {hasActions ? (
              <th className={`${headCell} text-center`}>{actionsLabel}</th>
            ) : null}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={rowKey(row, index)} className="border-b border-border hover:bg-muted/30">
              {showIndex ? (
                <td className="border border-border p-1 align-middle text-center text-xs text-muted-foreground">
                  {index + 1}
                </td>
              ) : null}
              {columns.map((column) => (
                <td key={column.key} className={bodyCell}>
                  {column.cell(row, index)}
                </td>
              ))}
              {hasActions ? (
                <td className="border border-border p-1 align-middle text-center text-xs">
                  <div className="flex h-7 items-center justify-center">
                    {actions?.(row, index)}
                    {onRemove ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        onClick={() => onRemove(index)}
                        disabled={!canRemove}
                        aria-label={`${removeLabel ?? "Remove"} ${index + 1}`}
                        className="text-muted-foreground hover:text-destructive"
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    ) : null}
                  </div>
                </td>
              ) : null}
            </tr>
          ))}
        </tbody>
        {summary && summary.length > 0 ? (
          <tfoot>
            {/* One merged cell spanning every column: no internal grid lines
                and no stacked rows — label/value pairs sit inline, and only
                the boxed inputs mark where the user can type. */}
            <tr>
              <td
                className="border-t border-border bg-muted/20 p-1.5"
                colSpan={totalSpan}
              >
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5">
                  {footerLeading ?? null}
                  <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-1.5 text-xs">
                    {summary.map((row) => (
                      <div key={row.key} className="flex items-center gap-1.5">
                        <span
                          className={
                            row.strong ? "font-bold" : "text-muted-foreground"
                          }
                        >
                          {row.label}
                        </span>
                        <span
                          className={`tabular-nums${
                            row.strong ? " font-bold" : ""
                          }`}
                        >
                          {row.value}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </td>
            </tr>
          </tfoot>
        ) : null}
      </table>
    </div>
  );
}
