"use client";

import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { unwrap, withAuth } from "@/lib/api-client";
import { organizationCampusesIndex } from "@/lib/api/campus/campus";
import { organizationDepartmentsIndex } from "@/lib/api/department/department";
import { organizationDivisionsIndex } from "@/lib/api/division/division";
import type { EmployeeProfileValues } from "../employee-profile-form";

type LookupOption = {
  id: string;
  name: string;
};

type EmployeeProfileFieldsProps = {
  idPrefix: string;
  values: EmployeeProfileValues;
  onChange: (values: EmployeeProfileValues) => void;
  enabled?: boolean;
  fieldErrors?: Record<string, string>;
  onClearError?: (field: string) => void;
};

export function EmployeeProfileFields({
  idPrefix,
  values,
  onChange,
  enabled = true,
  fieldErrors,
  onClearError,
}: EmployeeProfileFieldsProps) {
  const t = useTranslations("employeeProfiles");

  const campusesQuery = useQuery({
    queryKey: ["campuses"],
    queryFn: async () =>
      unwrap<LookupOption[]>(await organizationCampusesIndex(withAuth())),
    enabled,
    staleTime: 5 * 60 * 1000,
  });
  const divisionsQuery = useQuery({
    queryKey: ["divisions"],
    queryFn: async () =>
      unwrap<LookupOption[]>(await organizationDivisionsIndex(withAuth())),
    enabled,
    staleTime: 5 * 60 * 1000,
  });
  const departmentsQuery = useQuery({
    queryKey: ["departments"],
    queryFn: async () =>
      unwrap<LookupOption[]>(await organizationDepartmentsIndex(withAuth())),
    enabled,
    staleTime: 5 * 60 * 1000,
  });

  function setValue<K extends keyof EmployeeProfileValues>(
    key: K,
    value: EmployeeProfileValues[K],
    errorKey: string,
  ) {
    onChange({ ...values, [key]: value });
    onClearError?.(errorKey);
  }

  return (
    <>
      <div className="flex items-center gap-3">
        <Label
          htmlFor={`${idPrefix}-card-id`}
          className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
        >
          {t("cardId")}
        </Label>
        <div className="min-w-0 flex-1 space-y-1">
          <Input
            id={`${idPrefix}-card-id`}
            value={values.cardId}
            onChange={(e) => setValue("cardId", e.target.value, "card_id")}
            placeholder={t("cardIdPlaceholder")}
          />
          {fieldErrors?.card_id && (
            <p className="text-xs text-destructive">{fieldErrors.card_id}</p>
          )}
        </div>
      </div>
      <div className="flex items-center gap-3">
        <Label
          htmlFor={`${idPrefix}-position`}
          className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
        >
          {t("position")}
        </Label>
        <div className="min-w-0 flex-1 space-y-1">
          <Input
            id={`${idPrefix}-position`}
            value={values.position}
            onChange={(e) => setValue("position", e.target.value, "position")}
            placeholder={t("positionPlaceholder")}
          />
          {fieldErrors?.position && (
            <p className="text-xs text-destructive">{fieldErrors.position}</p>
          )}
        </div>
      </div>
      <div className="flex items-center gap-3">
        <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
          {t("campus")}
        </Label>
        <div className="min-w-0 flex-1 space-y-1">
          <Select
            value={values.campusId}
            onValueChange={(value) =>
              setValue("campusId", value ?? null, "campus_id")
            }
            items={[
              { value: null, label: t("none") },
              ...(campusesQuery.data ?? []).map((campus) => ({
                value: campus.id,
                label: campus.name,
              })),
            ]}
          >
            <SelectTrigger className="min-w-0 flex-1">
              <SelectValue placeholder={t("none")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={null}>{t("none")}</SelectItem>
              {(campusesQuery.data ?? []).map((campus) => (
                <SelectItem key={campus.id} value={campus.id}>
                  {campus.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {fieldErrors?.campus_id && (
            <p className="text-xs text-destructive">{fieldErrors.campus_id}</p>
          )}
        </div>
      </div>
      <div className="flex items-center gap-3">
        <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
          {t("division")}
        </Label>
        <div className="min-w-0 flex-1 space-y-1">
          <Select
            value={values.divisionId}
            onValueChange={(value) =>
              setValue("divisionId", value ?? null, "division_id")
            }
            items={[
              { value: null, label: t("none") },
              ...(divisionsQuery.data ?? []).map((division) => ({
                value: division.id,
                label: division.name,
              })),
            ]}
          >
            <SelectTrigger className="min-w-0 flex-1">
              <SelectValue placeholder={t("none")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={null}>{t("none")}</SelectItem>
              {(divisionsQuery.data ?? []).map((division) => (
                <SelectItem key={division.id} value={division.id}>
                  {division.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {fieldErrors?.division_id && (
            <p className="text-xs text-destructive">
              {fieldErrors.division_id}
            </p>
          )}
        </div>
      </div>
      <div className="flex items-center gap-3">
        <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
          {t("department")}
        </Label>
        <div className="min-w-0 flex-1 space-y-1">
          <Select
            value={values.departmentId}
            onValueChange={(value) =>
              setValue("departmentId", value ?? null, "department_id")
            }
            items={[
              { value: null, label: t("none") },
              ...(departmentsQuery.data ?? []).map((department) => ({
                value: department.id,
                label: department.name,
              })),
            ]}
          >
            <SelectTrigger className="min-w-0 flex-1">
              <SelectValue placeholder={t("none")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={null}>{t("none")}</SelectItem>
              {(departmentsQuery.data ?? []).map((department) => (
                <SelectItem key={department.id} value={department.id}>
                  {department.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {fieldErrors?.department_id && (
            <p className="text-xs text-destructive">
              {fieldErrors.department_id}
            </p>
          )}
        </div>
      </div>
    </>
  );
}
