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
import { resolveBreadcrumbs } from "@/lib/breadcrumbs"

export function BreadcrumbBar() {
  const pathname = usePathname()
  const tNav = useTranslations("nav")
  const tBreadcrumb = useTranslations("breadcrumb")

  const crumbs = resolveBreadcrumbs(pathname)

  if (crumbs.length === 0) return null

  function getLabel(key: string): string {
    if (key.startsWith("nav.")) {
      return tNav(key.replace("nav.", ""))
    }
    return tBreadcrumb(key.replace("breadcrumb.", ""))
  }

  return (
    <nav className="flex h-8 shrink-0 items-center gap-1 bg-background px-6 text-sm">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink render={<Link href="/" />}>
              <Home className="size-3.5" />
            </BreadcrumbLink>
          </BreadcrumbItem>

          {crumbs.map((crumb) => (
            <Fragment key={crumb.href}>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                {crumb.isCurrent ? (
                  <BreadcrumbPage>{getLabel(crumb.labelKey)}</BreadcrumbPage>
                ) : (
                  <BreadcrumbLink render={<Link href={crumb.href} />}>
                    {getLabel(crumb.labelKey)}
                  </BreadcrumbLink>
                )}
              </BreadcrumbItem>
            </Fragment>
          ))}
        </BreadcrumbList>
      </Breadcrumb>
    </nav>
  )
}
