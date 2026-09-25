"use client"

import { SidebarTrigger } from "@/components/ui/sidebar"
import { Separator } from "@/components/ui/separator"
import { ThemeToggle } from "@/components/layout/theme-toggle"
import { UserMenu } from "@/components/layout/user-menu"
import { NotificationsMenu } from "@/components/layout/notifications-menu"
import type { UserResource } from "@/lib/api/model"

type Props = {
  user: UserResource | null
}

export function Topbar({ user }: Props) {
  return (
    <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3 border-b bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
      <SidebarTrigger />
      <Separator orientation="vertical" className="h-5" />

      <div className="flex flex-1 items-center" />

      <div className="flex items-center gap-1.5">
        <ThemeToggle />
        <NotificationsMenu />
        <UserMenu user={user} />
      </div>
    </header>
  )
}
