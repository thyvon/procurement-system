export type PeriodKey = "month" | "quarter" | "year";

export type KpiSummary = {
  totalSpendUsd: number;
  spendDelta: string;
  poSpendUsd: number;
  invoiceSpendUsd: number;
  pendingApprovalsCount: number;
  pendingApprovalsUsd: number;
  urgentApprovalsCount: number;
  activeProcurementsCount: number;
  openPosCount: number;
  openEvaluationsCount: number;
  openPaymentsCount: number;
  openPaymentsUsd: number;
  clearancePendingCount: number;
};

export type MonthlySpendPoint = {
  monthKey: "jun" | "jul" | "aug" | "sep" | "oct" | "nov";
  purchaseOrdersUsd: number;
  directInvoicesUsd: number;
  cashRequestsUsd: number;
};

export type SpendBreakdownItem = {
  key: "purchaseOrders" | "directInvoices" | "advanceRequests" | "pettyCash";
  amountUsd: number;
  sharePercent: number;
  documentsCount: number;
  barClass: string;
  dotClass: string;
};

export type PendingApprovalRow = {
  id: string;
  code: string;
  docTypeKey:
    | "evaluation"
    | "pettyCash"
    | "advanceRequest"
    | "directInvoice"
    | "advanceLiquidation"
    | "cashClearance";
  title: string;
  department: string;
  campus: string;
  currentStep: string;
  submittedBy: string;
  submittedDate: string;
  amountUsd: number;
  href: string;
};

export type RecentDocumentRow = {
  id: string;
  code: string;
  docTypeKey:
    | "purchaseOrder"
    | "evaluation"
    | "directInvoice"
    | "pettyCash"
    | "advanceRequest"
    | "cashClearance"
    | "advanceLiquidation";
  CounterpartyOrRequester: string;
  department: string;
  date: string;
  amountUsd: number;
  status: "approved" | "pending" | "returned" | "rejected";
  href: string;
};

export const KPI_BY_PERIOD: Record<PeriodKey, KpiSummary> = {
  month: {
    totalSpendUsd: 148_420,
    spendDelta: "+12.4%",
    poSpendUsd: 89_650,
    invoiceSpendUsd: 58_770,
    pendingApprovalsCount: 14,
    pendingApprovalsUsd: 38_950,
    urgentApprovalsCount: 4,
    activeProcurementsCount: 28,
    openPosCount: 19,
    openEvaluationsCount: 9,
    openPaymentsCount: 16,
    openPaymentsUsd: 14_280,
    clearancePendingCount: 6,
  },
  quarter: {
    totalSpendUsd: 412_890,
    spendDelta: "+8.7%",
    poSpendUsd: 254_100,
    invoiceSpendUsd: 158_790,
    pendingApprovalsCount: 14,
    pendingApprovalsUsd: 38_950,
    urgentApprovalsCount: 4,
    activeProcurementsCount: 64,
    openPosCount: 45,
    openEvaluationsCount: 19,
    openPaymentsCount: 34,
    openPaymentsUsd: 29_640,
    clearancePendingCount: 11,
  },
  year: {
    totalSpendUsd: 1_584_300,
    spendDelta: "+15.2%",
    poSpendUsd: 982_400,
    invoiceSpendUsd: 601_900,
    pendingApprovalsCount: 14,
    pendingApprovalsUsd: 38_950,
    urgentApprovalsCount: 4,
    activeProcurementsCount: 215,
    openPosCount: 162,
    openEvaluationsCount: 53,
    openPaymentsCount: 92,
    openPaymentsUsd: 84_120,
    clearancePendingCount: 18,
  },
};

export const MONTHLY_SPEND_TREND: MonthlySpendPoint[] = [
  {
    monthKey: "jun",
    purchaseOrdersUsd: 62_400,
    directInvoicesUsd: 28_100,
    cashRequestsUsd: 14_200,
  },
  {
    monthKey: "jul",
    purchaseOrdersUsd: 71_800,
    directInvoicesUsd: 31_500,
    cashRequestsUsd: 16_900,
  },
  {
    monthKey: "aug",
    purchaseOrdersUsd: 58_200,
    directInvoicesUsd: 26_400,
    cashRequestsUsd: 12_800,
  },
  {
    monthKey: "sep",
    purchaseOrdersUsd: 84_600,
    directInvoicesUsd: 36_200,
    cashRequestsUsd: 19_400,
  },
  {
    monthKey: "oct",
    purchaseOrdersUsd: 79_300,
    directInvoicesUsd: 34_800,
    cashRequestsUsd: 17_600,
  },
  {
    monthKey: "nov",
    purchaseOrdersUsd: 89_650,
    directInvoicesUsd: 38_590,
    cashRequestsUsd: 20_180,
  },
];

export const SPEND_BREAKDOWN: SpendBreakdownItem[] = [
  {
    key: "purchaseOrders",
    amountUsd: 65_305,
    sharePercent: 44,
    documentsCount: 19,
    barClass: "bg-chart-1",
    dotClass: "bg-chart-1",
  },
  {
    key: "directInvoices",
    amountUsd: 38_589,
    sharePercent: 26,
    documentsCount: 24,
    barClass: "bg-chart-2",
    dotClass: "bg-chart-2",
  },
  {
    key: "advanceRequests",
    amountUsd: 23_747,
    sharePercent: 16,
    documentsCount: 11,
    barClass: "bg-chart-3",
    dotClass: "bg-chart-3",
  },
  {
    key: "pettyCash",
    amountUsd: 20_779,
    sharePercent: 14,
    documentsCount: 31,
    barClass: "bg-chart-4",
    dotClass: "bg-chart-4",
  },
];

export const PENDING_APPROVALS: PendingApprovalRow[] = [
  {
    id: "apr-1",
    code: "EVL-26-042",
    docTypeKey: "evaluation",
    title: "IT Network Switches & Campus Wi-Fi Upgrade",
    department: "Information Technology",
    campus: "Main Campus",
    currentStep: "Finance Director",
    submittedBy: "Sokha Chan",
    submittedDate: "2026-11-09",
    amountUsd: 18_450,
    href: "/approvals",
  },
  {
    id: "apr-2",
    code: "INV-26-089",
    docTypeKey: "directInvoice",
    title: "Science Laboratory Reagents & Glassware",
    department: "Science Faculty",
    campus: "Toul Kork Campus",
    currentStep: "Procurement Manager",
    submittedBy: "Dara Vann",
    submittedDate: "2026-11-09",
    amountUsd: 6_780,
    href: "/approvals",
  },
  {
    id: "apr-3",
    code: "ADV-26-031",
    docTypeKey: "advanceRequest",
    title: "Annual Inter-Campus Graduation Ceremony",
    department: "Student Affairs",
    campus: "Main Campus",
    currentStep: "Division Manager",
    submittedBy: "Bopha Meas",
    submittedDate: "2026-11-08",
    amountUsd: 4_500,
    href: "/approvals",
  },
  {
    id: "apr-4",
    code: "EVL-26-044",
    docTypeKey: "evaluation",
    title: "Classroom Air Conditioning Units (12 Sets)",
    department: "Facilities Management",
    campus: "Chbar Ampov Campus",
    currentStep: "Executive Committee",
    submittedBy: "Rithy Kem",
    submittedDate: "2026-11-08",
    amountUsd: 7_920,
    href: "/approvals",
  },
  {
    id: "apr-5",
    code: "ALQ-26-019",
    docTypeKey: "advanceLiquidation",
    title: "Regional Teacher Training Workshop Liquidation",
    department: "Human Resources",
    campus: "Siem Reap Branch",
    currentStep: "Finance Verification",
    submittedBy: "Nary Pich",
    submittedDate: "2026-11-07",
    amountUsd: 1_960,
    href: "/approvals",
  },
  {
    id: "apr-6",
    code: "PCR-26-108",
    docTypeKey: "pettyCash",
    title: "Urgent Electrical & Plumbing Maintenance Supplies",
    department: "Facilities Management",
    campus: "Main Campus",
    currentStep: "Department Head",
    submittedBy: "Vireak Lim",
    submittedDate: "2026-11-10",
    amountUsd: 485,
    href: "/approvals",
  },
];

export const RECENT_DOCUMENTS: RecentDocumentRow[] = [
  {
    id: "doc-1",
    code: "PO-26-114",
    docTypeKey: "purchaseOrder",
    CounterpartyOrRequester: "Anajak Computer Co., Ltd",
    department: "Information Technology",
    date: "2026-11-10",
    amountUsd: 12_600,
    status: "approved",
    href: "/purchase-orders/list",
  },
  {
    id: "doc-2",
    code: "CCL-26-052",
    docTypeKey: "cashClearance",
    CounterpartyOrRequester: "Sovannarith Keo",
    department: "Administration",
    date: "2026-11-10",
    amountUsd: 840,
    status: "pending",
    href: "/payments/cash-clearances",
  },
  {
    id: "doc-3",
    code: "INV-26-088",
    docTypeKey: "directInvoice",
    CounterpartyOrRequester: "IBC Stationery & Office Supply",
    department: "Academic Operations",
    date: "2026-11-09",
    amountUsd: 2_310,
    status: "approved",
    href: "/invoices/direct-purchase-inv",
  },
  {
    id: "doc-4",
    code: "EVL-26-041",
    docTypeKey: "evaluation",
    CounterpartyOrRequester: "3 Quotations Compared",
    department: "Facilities Management",
    date: "2026-11-08",
    amountUsd: 9_450,
    status: "returned",
    href: "/purchase-orders/evaluations",
  },
  {
    id: "doc-5",
    code: "ADV-26-029",
    docTypeKey: "advanceRequest",
    CounterpartyOrRequester: "Channary Ouk",
    department: "Marketing & Admissions",
    date: "2026-11-07",
    amountUsd: 3_150,
    status: "approved",
    href: "/payments/advance-requests",
  },
  {
    id: "doc-6",
    code: "PCR-26-104",
    docTypeKey: "pettyCash",
    CounterpartyOrRequester: "Piseth Hong",
    department: "Transport & Fleet",
    date: "2026-11-06",
    amountUsd: 320,
    status: "rejected",
    href: "/payments/petty-cash-requests",
  },
];
