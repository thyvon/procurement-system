"use client";

import type { ReactNode } from "react";
import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { RequiredMark } from "@/components/required-mark";

export const headCell =
  "border border-border bg-muted/60 px-2 py-1.5 text-left text-sm font-medium";
export const bodyCell = "border border-border p-1 align-top text-sm";
export const cellInputClass =
  "h-8 rounded-none border-border/60 bg-background px-1.5 text-sm shadow-none placeholder:text-sm md:text-sm";
export const cellInputRightClass = `${cellInputClass} text-right`;
/** Boxed input for the totals footer — it is not a grid cell. */
export const summaryInputClass =
  "h-8 w-28 rounded-none border border-border bg-background px-1.5 text-right text-sm tabular-nums shadow-none placeholder:text-sm md:text-sm";

const ALIGN_CLASS: Record<string, string> = {
  left: "text-left",
  right: "text-right",
  center: "text-center",
};

/**
 * Tailwind width tokens → px, used to budget the table's min-width.
 * Columns pass their width as the same token applied to the `<col>`; add a
 * token here when a caller introduces a new width class.
 */
const WIDTH_PX: Record<string, number> = {
  "w-8": 32,
  "w-10": 40,
  "w-20": 80,
  "w-24": 96,
  "w-28": 112,
  "w-32": 128,
  "w-36": 144,
  "w-40": 160,
  "w-44": 176,
  "w-48": 192,
  "w-56": 224,
  "w-64": 256,
  "w-72": 288,
  "w-80": 320,
  "w-96": 384,
};

/**
 * Table min-width budget in px, computed from the columns actually passed —
 * the same pattern as the evaluation matrix's `tableWidth`: fixed px
 * columns don't shrink on zoom while the viewport does, so without this
 * floor the wrapper stops scrolling, the flexible description column is
 * squeezed, and its header bleeds into the next cell. Below the budget this
 * wrapper scrolls instead; above it the table fills the card body and the
 * width-less description column absorbs the slack while every fixed column
 * keeps its token width. A thin table doesn't inherit a floor built for
 * the widest one.
 */
const DESCRIPTION_MIN_WIDTH = 256; // flexible column (no width token)
const INDEX_COLUMN_WIDTH = 32; // showIndex
const ACTIONS_COLUMN_WIDTH = 40; // actions / onRemove

export type LineItemsColumn<T> = {
  key: string;
  label: string;
  width?: string;
  align?: "left" | "right" | "center";
  /** Marks the header label as a required cell (the API enforces it). */
  required?: boolean;
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
 * Spreadsheet-flavoured editable grid: flush bordered cells, h-8 inputs and a
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
  const fixedWidth = columns.reduce(
    (sum, column) => sum + (column.width ? (WIDTH_PX[column.width] ?? 0) : 0),
    0
  );
  const flexibleColumns = columns.filter((column) => !column.width).length;
  const tableWidth =
    fixedWidth +
    flexibleColumns * DESCRIPTION_MIN_WIDTH +
    (showIndex ? INDEX_COLUMN_WIDTH : 0) +
    (hasActions ? ACTIONS_COLUMN_WIDTH : 0);

  /**
   * Excel-ish cell navigation: ↑/↓/Enter move between rows in the same
   * column; ←/→ jump to the neighbouring cell (for text inputs only at the
   * caret edges, since number inputs have no caret). Runs in the capture
   * phase so combobox inputs don't swallow the keys — except while their
   * popup is open, where its own arrow handling wins.
   */
  function handleGridKeyDown(event: React.KeyboardEvent<HTMLTableSectionElement>) {
    const keys = ["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight", "Enter"];
    if (!keys.includes(event.key)) return;

    const target = event.target as HTMLElement;
    const cell = target.closest("td");
    if (!cell || !cell.closest("tbody")) return;

    // An open combobox popup owns the arrow keys.
    if (document.querySelector('[data-slot="combobox-content"][data-open]')) {
      return;
    }

    const isTextEntry =
      target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement;
    const isNumberInput =
      target instanceof HTMLInputElement && target.type === "number";

    // ←/→ at the caret edge only; number inputs have no caret (their
    // selectionStart is null), so arrows always jump between cells there.
    if (isTextEntry && !isNumberInput && (event.key === "ArrowLeft" || event.key === "ArrowRight")) {
      const value = target.value;
      const start = target.selectionStart ?? 0;
      const end = target.selectionEnd ?? 0;
      if (event.key === "ArrowLeft" && (start > 0 || end > 0)) return;
      if (event.key === "ArrowRight" && (start < value.length || end < value.length)) {
        return;
      }
    }

    // Enter types a newline in a textarea — leave it alone.
    if (event.key === "Enter" && target instanceof HTMLTextAreaElement) return;

    const row = cell.parentElement as HTMLTableRowElement | null;
    const body = row?.parentElement as HTMLTableSectionElement | null;
    if (!row || !body) return;

    const rowsEls = Array.from(body.rows);
    const rowIndex = rowsEls.indexOf(row);
    const cellIndex = cell.cellIndex;

    // ←/→ stay in the row; ↑/↓/Enter move between rows in the same column.
    const sameRow = event.key === "ArrowLeft" || event.key === "ArrowRight";
    const nextRowIndex = sameRow
      ? rowIndex
      : event.key === "ArrowUp"
        ? rowIndex - 1
        : rowIndex + 1;
    const nextCellIndex = sameRow
      ? cellIndex + (event.key === "ArrowLeft" ? -1 : 1)
      : cellIndex;

    const nextRowEl = rowsEls[nextRowIndex];
    const nextCell = nextRowEl?.cells[nextCellIndex] as HTMLElement | undefined;
    if (!nextCell) return;

    const focusable = nextCell.querySelector<HTMLElement>(
      'input:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable) return;

    event.preventDefault();
    event.stopPropagation();
    focusable.focus();
    // Caret at the edge we came from: end when moving left/up, start otherwise.
    // Number inputs don't support setSelectionRange — just focus them.
    if (
      focusable instanceof HTMLInputElement &&
      (focusable.type === "text" || focusable.type === "")
    ) {
      const atEnd = event.key === "ArrowLeft" || event.key === "ArrowUp";
      const pos = atEnd ? focusable.value.length : 0;
      focusable.setSelectionRange(pos, pos);
    }
  }

  return (
    <div className="w-full">
      <div className="w-full overflow-x-auto border border-border">
        <table
          className="w-full border-collapse text-sm table-fixed"
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
                  {column.label} {column.required ? <RequiredMark /> : null}
                </th>
              ))}
              {hasActions ? (
                <th className={`${headCell} text-center`}>{actionsLabel}</th>
              ) : null}
            </tr>
          </thead>
          {/* Capture phase: reach the key before the combobox input's own
              Base UI handler (which would open its popup on ↓/↑). */}
          <tbody onKeyDownCapture={handleGridKeyDown}>
            {rows.map((row, index) => (
              <tr key={rowKey(row, index)} className="border-b border-border hover:bg-muted/30">
                {showIndex ? (
                  <td className="border border-border p-1 align-middle text-center text-sm text-muted-foreground">
                    {index + 1}
                  </td>
                ) : null}
                {columns.map((column) => (
                  <td key={column.key} className={bodyCell}>
                    {column.cell(row, index)}
                  </td>
                ))}
                {hasActions ? (
                  <td className="border border-border p-1 align-middle text-center text-sm">
                    <div className="flex h-8 items-center justify-center">
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
        </table>
      </div>
      {/* Totals sit below the (scrollable) table so they stay visible even
          when the grid overflows horizontally: stacked label/value rows,
          right-aligned, with one hairline rule above the first grand-total
          row. */}
      {summary && summary.length > 0 ? (
        <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 border border-t-0 border-border bg-muted/20 p-2">
          {footerLeading ?? null}
          <div className="ml-auto flex min-w-56 flex-col gap-1">
            {summary.map((row, index) => {
              const divider = row.strong && !summary[index - 1]?.strong;
              return (
                <div
                  key={row.key}
                  className={`flex items-center justify-between gap-8 text-sm${
                    divider ? " mt-1 border-t border-border pt-1.5" : ""
                  }`}
                >
                  <span
                    className={
                      row.strong ? "font-semibold" : "text-muted-foreground"
                    }
                  >
                    {row.label}
                  </span>
                  <span
                    className={`tabular-nums text-right${
                      row.strong ? " text-base font-semibold" : ""
                    }`}
                  >
                    {row.value}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      ) : null}
    </div>
  );
}
