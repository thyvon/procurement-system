import type { StoreEmployeeProfileRequest } from "@/lib/api/model";

export type EmployeeProfileValues = {
  cardId: string;
  position: string;
  campusId: string | null;
  divisionId: string | null;
  departmentId: string | null;
};

export type EmployeeProfileLike = {
  cardId?: string | null;
  position?: string | null;
  campusId?: string | null;
  divisionId?: string | null;
  departmentId?: string | null;
};

export const EMPTY_EMPLOYEE_PROFILE: EmployeeProfileValues = {
  cardId: "",
  position: "",
  campusId: null,
  divisionId: null,
  departmentId: null,
};

export function employeeProfileValuesFrom(
  profile: EmployeeProfileLike | null | undefined,
): EmployeeProfileValues {
  return {
    cardId: profile?.cardId ?? "",
    position: profile?.position ?? "",
    campusId: profile?.campusId ?? null,
    divisionId: profile?.divisionId ?? null,
    departmentId: profile?.departmentId ?? null,
  };
}

export function employeeProfilePayload(
  values: EmployeeProfileValues,
): StoreEmployeeProfileRequest {
  return {
    card_id: values.cardId.trim() || null,
    position: values.position.trim() || null,
    campus_id: values.campusId,
    division_id: values.divisionId,
    department_id: values.departmentId,
  };
}

export function hasEmployeeProfile(values: EmployeeProfileValues): boolean {
  return Boolean(
    values.cardId.trim() ||
      values.position.trim() ||
      values.campusId ||
      values.divisionId ||
      values.departmentId,
  );
}
