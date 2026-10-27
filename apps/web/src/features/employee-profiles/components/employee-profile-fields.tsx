"use client";

import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import { unwrap, withAuth } from "@/lib/api-client";
import { organizationCampusesIndex } from "@/lib/api/campus/campus";
import { organizationDepartmentsIndex } from "@/lib/api/department/department";
import { organizationDivisionsIndex } from "@/lib/api/division/division";
import type { EmployeeProfileValues } from "../employee-profile-form";

type LookupOption = {
  id: string;
  name: string;
};

type DepartmentOption = LookupOption & {
  divisionIds?: string[];
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
      unwrap<DepartmentOption[]>(
        await organizationDepartmentsIndex(withAuth()),
      ),
    enabled,
    staleTime: 5 * 60 * 1000,
  });

  const departments = departmentsQuery.data ?? [];
  const divisionId = values.divisionId;
  const departmentOptions = divisionId
    ? departments.filter((department) =>
        (department.divisionIds ?? []).includes(divisionId),
      )
    : [];

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
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="space-y-1">
          <Label className="text-left after:ml-1 after:content-[':']">
            {t("campus")}
          </Label>
          <Combobox
            items={ComboboxNS.createItems(campusesQuery.data ?? [], {
              getValue: (campus) => campus.id,
              getLabel: (campus) => campus.name,
            })}
            value={values.campusId}
            itemToStringLabel={(id) =>
              campusesQuery.data?.find((campus) => campus.id === id)?.name ?? ""
            }
            onValueChange={(value) =>
              setValue("campusId", value ?? null, "campus_id")
            }
          >
            <ComboboxInput
              placeholder={t("none")}
              aria-label={t("campus")}
              showClear
              className="w-full"
            />
            <ComboboxContent>
              <ComboboxEmpty>{t("noCampuses")}</ComboboxEmpty>
              <ComboboxList>
                {(campus) => (
                  <ComboboxItem key={campus.id} value={campus.id}>
                    {campus.name}
                  </ComboboxItem>
                )}
              </ComboboxList>
            </ComboboxContent>
          </Combobox>
          {fieldErrors?.campus_id && (
            <p className="text-xs text-destructive">{fieldErrors.campus_id}</p>
          )}
        </div>
        <div className="space-y-1">
          <Label className="text-left after:ml-1 after:content-[':']">
            {t("division")}
          </Label>
          <Combobox
            items={ComboboxNS.createItems(divisionsQuery.data ?? [], {
              getValue: (division) => division.id,
              getLabel: (division) => division.name,
            })}
            value={values.divisionId}
            itemToStringLabel={(id) =>
              divisionsQuery.data?.find((division) => division.id === id)
                ?.name ?? ""
            }
            onValueChange={(value) => {
              const nextDivisionId = value;
              const valid =
                nextDivisionId != null &&
                departments.some(
                  (department) =>
                    department.id === values.departmentId &&
                    (department.divisionIds ?? []).includes(nextDivisionId),
                );
              onChange({
                ...values,
                divisionId: nextDivisionId,
                departmentId: valid ? values.departmentId : null,
              });
              onClearError?.("division_id");
              onClearError?.("department_id");
            }}
          >
            <ComboboxInput
              placeholder={t("none")}
              aria-label={t("division")}
              showClear
              className="w-full"
            />
            <ComboboxContent>
              <ComboboxEmpty>{t("noDivisions")}</ComboboxEmpty>
              <ComboboxList>
                {(division) => (
                  <ComboboxItem key={division.id} value={division.id}>
                    {division.name}
                  </ComboboxItem>
                )}
              </ComboboxList>
            </ComboboxContent>
          </Combobox>
          {fieldErrors?.division_id && (
            <p className="text-xs text-destructive">
              {fieldErrors.division_id}
            </p>
          )}
        </div>
        <div className="space-y-1">
          <Label className="text-left after:ml-1 after:content-[':']">
            {t("department")}
          </Label>
          <Combobox
            items={ComboboxNS.createItems(departmentOptions, {
              getValue: (department) => department.id,
              getLabel: (department) => department.name,
            })}
            value={values.departmentId}
            itemToStringLabel={(id) =>
              departments.find((department) => department.id === id)?.name ?? ""
            }
            onValueChange={(value) =>
              setValue("departmentId", value ?? null, "department_id")
            }
          >
            <ComboboxInput
              placeholder={t("none")}
              aria-label={t("department")}
              disabled={!values.divisionId}
              showClear
              className="w-full"
            />
            <ComboboxContent>
              <ComboboxEmpty>{t("noDepartments")}</ComboboxEmpty>
              <ComboboxList>
                {(department) => (
                  <ComboboxItem key={department.id} value={department.id}>
                    {department.name}
                  </ComboboxItem>
                )}
              </ComboboxList>
            </ComboboxContent>
          </Combobox>
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
