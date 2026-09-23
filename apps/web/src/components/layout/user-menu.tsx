"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { LogOut, UserRound } from "lucide-react";
import { authLogout } from "@/lib/api/auth/auth";
import { skipWelcomeLoader } from "@/features/auth/welcome-loader";
import { ProfileDialog } from "@/features/profile/profile-dialog";
import type { UserResource } from "@/lib/api/model";
import {
  authHeaders,
  clearTokens,
} from "@/lib/auth/token-store";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

type Props = {
  user: UserResource | null;
};

export function UserMenu({ user }: Props) {
  const t = useTranslations("topbar");
  const router = useRouter();
  const [profileOpen, setProfileOpen] = useState(false);
  const queryClient = useQueryClient();

  const logoutMutation = useMutation({
    mutationFn: () => authLogout({ headers: authHeaders() }),
    onSettled: () => {
      skipWelcomeLoader();
      clearTokens();
      queryClient.clear();
      router.push("/login");
    },
  });

  const initials = (user?.name ?? "?")
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <Button variant="ghost" className="h-9 gap-2 px-2" aria-label={t("profile")} />
          }
        >
          <Avatar className="size-7">
            {user?.avatar && <AvatarImage src={user.avatar} alt="" />}
            <AvatarFallback className="text-xs">{initials}</AvatarFallback>
          </Avatar>
          <span className="hidden text-sm font-medium sm:inline">
            {user?.name}
          </span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-56">
          <DropdownMenuGroup>
            <DropdownMenuLabel>
              <div className="text-sm font-medium">{user?.name}</div>
              <div className="text-xs font-normal text-muted-foreground">
                {user?.email}
              </div>
            </DropdownMenuLabel>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => setProfileOpen(true)}>
            <UserRound className="mr-2 h-4 w-4" />
            {t("profile")}
          </DropdownMenuItem>
          <DropdownMenuItem
            onClick={() => logoutMutation.mutate()}
            disabled={logoutMutation.isPending}
          >
            <LogOut className="mr-2 h-4 w-4" />
            {t("signOut")}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      {user && (
        <ProfileDialog
          user={user}
          open={profileOpen}
          onOpenChange={setProfileOpen}
        />
      )}
    </>
  );
}

export function TopbarUserIcon() {
  return <UserRound className="size-4" />;
}
