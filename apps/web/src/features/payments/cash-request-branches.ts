import {
  paymentsAdvanceRequestsDestroy,
  paymentsAdvanceRequestsIndex,
  paymentsAdvanceRequestsShow,
  paymentsAdvanceRequestsStore,
  paymentsAdvanceRequestsUpdate,
} from "@/lib/api/advance-request/advance-request";
import {
  paymentsCashRequestsDestroy,
  paymentsCashRequestsIndex,
  paymentsCashRequestsShow,
  paymentsCashRequestsStore,
  paymentsCashRequestsUpdate,
} from "@/lib/api/cash-request/cash-request";
import {
  paymentsPettyCashRequestsDestroy,
  paymentsPettyCashRequestsIndex,
  paymentsPettyCashRequestsShow,
  paymentsPettyCashRequestsStore,
  paymentsPettyCashRequestsUpdate,
} from "@/lib/api/petty-cash-request/petty-cash-request";
import type { PaymentsCashRequestsIndexParams } from "@/lib/api/model/paymentsCashRequestsIndexParams";
import type { StoreCashRequestRequest } from "@/lib/api/model/storeCashRequestRequest";
import type { UpdateCashRequestRequest } from "@/lib/api/model/updateCashRequestRequest";
import type { CashRequestType } from "./cash-request-helpers";

/**
 * The three cash request branches. Each one owns a single CashRequest type:
 * the route segment (and the API URI behind it) is the type, so one tab, one
 * form and one detail page serve all three by taking this key.
 */
export type CashRequestBranch =
  | "petty-cash-requests"
  | "advance-requests"
  | "cash-requests";

/** `nav.<key>` and `payments.<key>` — the branch's label in both namespaces. */
export type CashRequestBranchTKey =
  | "pettyCashRequests"
  | "advanceRequests"
  | "cashRequests";

interface Envelope {
  status: number;
  data: unknown;
}

interface CashRequestBranchClient {
  index(params?: PaymentsCashRequestsIndexParams, options?: RequestInit): Promise<Envelope>;
  show(cashRequest: string, options?: RequestInit): Promise<Envelope>;
  store(payload: StoreCashRequestRequest, options?: RequestInit): Promise<Envelope>;
  update(
    cashRequest: string,
    payload: UpdateCashRequestRequest,
    options?: RequestInit
  ): Promise<Envelope>;
  destroy(cashRequest: string, options?: RequestInit): Promise<Envelope>;
}

export interface CashRequestBranchConfig {
  /** The single CashRequest type this branch lists, creates and may touch. */
  type: CashRequestType;
  tKey: CashRequestBranchTKey;
  client: CashRequestBranchClient;
}

export const CASH_REQUEST_BRANCHES: Record<CashRequestBranch, CashRequestBranchConfig> = {
  "petty-cash-requests": {
    type: "petty_cash",
    tKey: "pettyCashRequests",
    client: {
      index: paymentsPettyCashRequestsIndex,
      show: paymentsPettyCashRequestsShow,
      store: paymentsPettyCashRequestsStore,
      update: paymentsPettyCashRequestsUpdate,
      destroy: paymentsPettyCashRequestsDestroy,
    },
  },
  "advance-requests": {
    type: "advance",
    tKey: "advanceRequests",
    client: {
      index: paymentsAdvanceRequestsIndex,
      show: paymentsAdvanceRequestsShow,
      store: paymentsAdvanceRequestsStore,
      update: paymentsAdvanceRequestsUpdate,
      destroy: paymentsAdvanceRequestsDestroy,
    },
  },
  "cash-requests": {
    type: "cash",
    tKey: "cashRequests",
    client: {
      index: paymentsCashRequestsIndex,
      show: paymentsCashRequestsShow,
      store: paymentsCashRequestsStore,
      update: paymentsCashRequestsUpdate,
      destroy: paymentsCashRequestsDestroy,
    },
  },
};
