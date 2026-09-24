"use client"

import { Fragment } from "react"
import Link from "next/link"
import { useTranslations } from "next-intl"
import { usePathname } from "next/navigation"
import { Home } from "lucide-react"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"

const SEGMENT_LABELS: Record<string, string> = {
  products: "nav.products",
  users: "nav.users",
  create: "breadcrumb.create",
  edit: "breadcrumb.edit",
}

export function BreadcrumbBar() {
  const pathname = usePathname()
  const tNav = useTranslations("nav")
  const tBreadcrumb = useTranslations("breadcrumb")

  const segments = pathname.split("/").filter(Boolean)

  function getLabel(segment: string): string {
    const key = SEGMENT_LABELS[segment]
    if (!key) return segment

    if (key.startsWith("nav.")) {
      return tNav(key.replace("nav.", ""))
    }
    return tBreadcrumb(key.replace("breadcrumb.", ""))
  }

  if (segments.length === 0) return null

  return (
    <nav className="flex h-8 shrink-0 items-center gap-1 bg-background px-6 text-sm">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink render={<Link href="/" />}>
              <Home className="size-3.5" />
            </BreadcrumbLink>
          </BreadcrumbItem>

          {segments.map((segment, index) => {
            const isLast = index === segments.length - 1
            const href = "/" + segments.slice(0, index + 1).join("/")
            const label = getLabel(segment)

            return (
              <Fragment key={href}>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                  {isLast ? (
                    <BreadcrumbPage>{label}</BreadcrumbPage>
                  ) : (
                    <BreadcrumbLink render={<Link href={href} />}>
                      {label}
                    </BreadcrumbLink>
                  )}
                </BreadcrumbItem>
              </Fragment>
            )
          })}
        </BreadcrumbList>
      </Breadcrumb>
    </nav>
  )
}
