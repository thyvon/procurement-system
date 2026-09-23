"use client"

import * as React from "react"
import { SearchIcon, XIcon, FilterIcon } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

interface FilterOption {
  label: string
  value: string
}

interface DataTableToolbarProps {
  searchValue?: string
  onSearchChange?: (value: string) => void
  searchPlaceholder?: string
  filterColumn?: string
  filterValue?: string
  onFilterChange?: (value: string) => void
  filterOptions?: FilterOption[]
  filterPlaceholder?: string
  children?: React.ReactNode
}

export function DataTableToolbar({
  searchValue = "",
  onSearchChange,
  searchPlaceholder = "Search...",
  filterColumn,
  filterValue = "",
  onFilterChange,
  filterOptions = [],
  filterPlaceholder = "All",
  children,
}: DataTableToolbarProps) {
  return (
    <div className="flex items-center gap-2">
      <div className="relative">
        <SearchIcon className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          placeholder={searchPlaceholder}
          value={searchValue}
          onChange={(e) => onSearchChange?.(e.target.value)}
          className="h-8 w-full max-w-sm pl-8 pr-8"
        />
        {searchValue && (
          <Button
            variant="ghost"
            size="icon-xs"
            className="absolute right-1 top-1/2 -translate-y-1/2"
            onClick={() => onSearchChange?.("")}
          >
            <XIcon className="size-3" />
          </Button>
        )}
      </div>
      {filterColumn && filterOptions.length > 0 && (
        <div className="flex items-center gap-1.5">
          <FilterIcon className="size-3.5 text-muted-foreground" />
          <Select
            value={filterValue}
            onValueChange={(v) => onFilterChange?.(v ?? "")}
            items={[
              { value: "all", label: filterPlaceholder },
              ...filterOptions.map((o) => ({ value: o.value, label: o.label })),
            ]}
          >
            <SelectTrigger className="h-8">
              <SelectValue placeholder={filterPlaceholder} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{filterPlaceholder}</SelectItem>
              {filterOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
      {children}
    </div>
  )
}
