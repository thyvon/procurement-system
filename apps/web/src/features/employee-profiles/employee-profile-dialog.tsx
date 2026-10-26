"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  usersEmployeeProfileShow,
  usersEmployeeProfilesIndex,
  usersEmployeeProfilesStore,
} from "@/lib/api/employee-profile/employee-profile";
import type { EmployeeProfileResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
import { EmployeeProfileFields } from "./components/employee-profile-fields";
import {
  employeeProfilePayload,
  employeeProfileValuesFrom,
  hasEmployeeProfile,
  type EmployeeProfileValues,
} from "./employee-profile-form";

type EmployeeProfileDialogProps = {
  userId: number;
  userName: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

type ProfileTab = "current" | "history";

export function EmployeeProfileDialog({
  userId,
  userName,
  open,
  onOpenChange,
}: EmployeeProfileDialogProps) {
  const t = useTranslations("employeeProfiles");

  const currentQuery = useQuery({
    queryKey: ["employeeProfiles", userId],
    queryFn: async () =>
      unwrap<EmployeeProfileResource | null>(
        await usersEmployeeProfileShow(userId, withAuth()),
      ),
    enabled: open && userId > 0,
  });

  const historyQuery = useQuery({
    queryKey: ["employeeProfiles", userId, "history"],
    queryFn: async () =>
      unwrap<EmployeeProfileResource[]>(
        await usersEmployeeProfilesIndex(userId, withAuth()),
      ),
    enabled: open && userId > 0,
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
          <DialogDescription>
            {t("forUser", { name: userName })}
          </DialogDescription>
        </DialogHeader>

        {open &&
          (currentQuery.isPending ? (
            <p className="py-4 text-sm text-muted-foreground">
              {t("loading")}
            </p>
          ) : (
            <EmployeeProfileDialogBody
              key={`${userId}-${currentQuery.data?.id ?? "none"}`}
              userId={userId}
              profile={currentQuery.data ?? null}
              history={historyQuery.data ?? []}
              historyPending={historyQuery.isPending}
              onClose={() => onOpenChange(false)}
            />
          ))}
      </DialogContent>
    </Dialog>
  );
}

function EmployeeProfileDialogBody({
  userId,
  profile,
  history,
  historyPending,
  onClose,
}: {
  userId: number;
  profile: EmployeeProfileResource | null;
  history: EmployeeProfileResource[];
  historyPending: boolean;
  onClose: () => void;
}) {
  const t = useTranslations("employeeProfiles");
  const queryClient = useQueryClient();
  const [tab, setTab] = useState<ProfileTab>("current");
  const [values, setValues] = useState<EmployeeProfileValues>(() =>
    employeeProfileValuesFrom(profile),
  );

  const hasSavedProfile = hasEmployeeProfile(employeeProfileValuesFrom(profile));

  const saveMutation = useMutation({
    mutationFn: async () =>
      unwrap(
        await usersEmployeeProfilesStore(
          userId,
          employeeProfilePayload(values),
          withAuth(),
        ),
      ),
    onSuccess: () => {
      toast.success(t("saved"));
      // Prefix match also covers ["employeeProfiles", userId, "history"].
      queryClient.invalidateQueries({
        queryKey: ["employeeProfiles", userId],
      });
      queryClient.invalidateQueries({ queryKey: ["users"] });
      queryClient.invalidateQueries({ queryKey: ["me"] });
      onClose();
    },
  });

  function handleSave() {
    // A user with no stored profile who leaves every field blank would
    // otherwise create an all-null version row on the server.
    if (!hasSavedProfile && !hasEmployeeProfile(values)) {
      toast.info(t("nothingToSave"));
      return;
    }
    saveMutation.mutate();
  }

  return (
    <>
      <Tabs value={tab} onValueChange={(value) => setTab(value as ProfileTab)}>
        <TabsList>
          <TabsTrigger value="current">{t("current")}</TabsTrigger>
          <TabsTrigger value="history">{t("history")}</TabsTrigger>
        </TabsList>
      </Tabs>

      {tab === "current" ? (
        <div className="space-y-3 py-2">
          <EmployeeProfileFields
            idPrefix="employee-profile"
            values={values}
            onChange={setValues}
          />
        </div>
      ) : historyPending ? (
        <p className="py-4 text-sm text-muted-foreground">{t("loading")}</p>
      ) : history.length === 0 ? (
        <p className="py-4 text-sm text-muted-foreground">{t("noHistory")}</p>
      ) : (
        <div className="overflow-x-auto py-2">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t("effectiveFrom")}</TableHead>
                <TableHead>{t("effectiveTo")}</TableHead>
                <TableHead>{t("position")}</TableHead>
                <TableHead>{t("cardId")}</TableHead>
                <TableHead>{t("campus")}</TableHead>
                <TableHead>{t("division")}</TableHead>
                <TableHead>{t("department")}</TableHead>
                <TableHead>{t("status")}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {history.map((entry) => (
                <TableRow key={entry.id}>
                  <TableCell>{entry.effectiveFrom}</TableCell>
                  <TableCell>{entry.effectiveTo ?? "—"}</TableCell>
                  <TableCell>{entry.position ?? "—"}</TableCell>
                  <TableCell>{entry.cardId ?? "—"}</TableCell>
                  <TableCell>{entry.campusName ?? "—"}</TableCell>
                  <TableCell>{entry.divisionName ?? "—"}</TableCell>
                  <TableCell>{entry.departmentName ?? "—"}</TableCell>
                  <TableCell>
                    {entry.isCurrent ? (
                      <Badge>{t("currentBadge")}</Badge>
                    ) : (
                      <Badge variant="secondary">{t("historical")}</Badge>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <DialogFooter>
        {tab === "current" ? (
          <>
            <Button type="button" variant="outline" onClick={onClose}>
              {t("cancel")}
            </Button>
            <Button
              type="button"
              onClick={handleSave}
              disabled={saveMutation.isPending}
            >
              {saveMutation.isPending ? t("saving") : t("save")}
            </Button>
          </>
        ) : (
          <Button type="button" variant="outline" onClick={onClose}>
            {t("close")}
          </Button>
        )}
      </DialogFooter>
    </>
  );
}
