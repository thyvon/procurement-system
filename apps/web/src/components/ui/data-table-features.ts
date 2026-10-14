import {
  columnFilteringFeature,
  columnVisibilityFeature,
  createFilteredRowModel,
  createPaginatedRowModel,
  createSortedRowModel,
  filterFn_arrIncludes,
  filterFn_equalsString,
  filterFn_inDateRange,
  filterFn_inNumberRange,
  filterFn_includesString,
  filterFn_weakEquals,
  rowPaginationFeature,
  rowSelectionFeature,
  rowSortingFeature,
  sortFn_alphanumeric,
  sortFn_text,
  tableFeatures,
} from "@tanstack/react-table"

export const features = tableFeatures({
  columnFilteringFeature,
  columnVisibilityFeature,
  rowPaginationFeature,
  rowSelectionFeature,
  rowSortingFeature,
  filteredRowModel: createFilteredRowModel(),
  paginatedRowModel: createPaginatedRowModel(),
  sortedRowModel: createSortedRowModel(),
  // Auto-resolution picks a filterFn by the column's data type and silently
  // drops the filter when it is not registered here. The toolbar filter
  // always sends string values ("true"/"false", status codes), so `equals`
  // maps to the stringifying variant (String(true) === "true").
  filterFns: {
    includesString: filterFn_includesString,
    equals: filterFn_equalsString,
    weakEquals: filterFn_weakEquals,
    inNumberRange: filterFn_inNumberRange,
    inDateRange: filterFn_inDateRange,
    arrIncludes: filterFn_arrIncludes,
  },
  sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
})

export type DataTableFeatures = typeof features
