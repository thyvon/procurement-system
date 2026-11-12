import {
  invoicesDirectPurchaseInvoicesDestroy,
  invoicesDirectPurchaseInvoicesIndex,
  invoicesDirectPurchaseInvoicesShow,
  invoicesDirectPurchaseInvoicesStatus,
  invoicesDirectPurchaseInvoicesStore,
  invoicesDirectPurchaseInvoicesUpdate,
} from "@/lib/api/direct-purchase-invoice/direct-purchase-invoice";
import {
  invoicesProPurchaseInvoicesDestroy,
  invoicesProPurchaseInvoicesIndex,
  invoicesProPurchaseInvoicesShow,
  invoicesProPurchaseInvoicesStatus,
  invoicesProPurchaseInvoicesStore,
  invoicesProPurchaseInvoicesUpdate,
} from "@/lib/api/pro-purchase-invoice/pro-purchase-invoice";
import type { InvoicesDirectPurchaseInvoicesIndexParams } from "@/lib/api/model/invoicesDirectPurchaseInvoicesIndexParams";
import type { InvoicesDirectPurchaseInvoicesIndexStatus } from "@/lib/api/model/invoicesDirectPurchaseInvoicesIndexStatus";
import type { StoreInvoiceRequest } from "@/lib/api/model/storeInvoiceRequest";
import type { UpdateInvoiceRequest } from "@/lib/api/model/updateInvoiceRequest";
import type { UpdateInvoiceStatusRequest } from "@/lib/api/model/updateInvoiceStatusRequest";

/**
 * The two invoice branches. Each one owns a single Invoice type: the route
 * segment (and the API URI behind it) is the type, so one tab, one form and
 * one detail page serve both by taking this key.
 */
export type InvoiceBranch = "direct-purchase-inv" | "pro-purchase-inv";

/** The index query params both branch clients accept (structurally identical). */
export type InvoiceIndexParams = InvoicesDirectPurchaseInvoicesIndexParams;

/** The status filter values both branch indexes accept. */
export type InvoiceIndexStatus = InvoicesDirectPurchaseInvoicesIndexStatus;

interface Envelope {
  status: number;
  data: unknown;
}

interface InvoiceBranchClient {
  index(params?: InvoiceIndexParams, options?: RequestInit): Promise<Envelope>;
  show(invoice: string, options?: RequestInit): Promise<Envelope>;
  store(payload: StoreInvoiceRequest, options?: RequestInit): Promise<Envelope>;
  update(
    invoice: string,
    payload: UpdateInvoiceRequest,
    options?: RequestInit
  ): Promise<Envelope>;
  status(
    invoice: string,
    payload: UpdateInvoiceStatusRequest,
    options?: RequestInit
  ): Promise<Envelope>;
  destroy(invoice: string, options?: RequestInit): Promise<Envelope>;
}

interface InvoiceBranchConfig {
  /** The single Invoice type this branch lists, creates and may touch. */
  type: StoreInvoiceRequest["type"];
  client: InvoiceBranchClient;
  /**
   * Lines may be linked to a PR/PO item from the ePurchase system — the
   * picker button, the Source column and the "at least one sourced line"
   * save gate only exist on branches that set this.
   */
  sourcedLines: boolean;
  /**
   * Supplier fields are picked from the ePurchase supplier directory
   * (searchable combobox) instead of typed free-hand.
   */
  supplierPicker: boolean;
  /**
   * The `*.manage` permission this branch's API policy checks — each
   * invoice branch owns its own permission pair.
   */
  managePermission: "direct_purchase_invoices.manage" | "pro_purchase_invoices.manage";
}

export const INVOICE_BRANCHES: Record<InvoiceBranch, InvoiceBranchConfig> = {
  "direct-purchase-inv": {
    type: "direct_purchase",
    sourcedLines: false,
    supplierPicker: false,
    managePermission: "direct_purchase_invoices.manage",
    client: {
      index: invoicesDirectPurchaseInvoicesIndex,
      show: invoicesDirectPurchaseInvoicesShow,
      store: invoicesDirectPurchaseInvoicesStore,
      update: invoicesDirectPurchaseInvoicesUpdate,
      status: invoicesDirectPurchaseInvoicesStatus,
      destroy: invoicesDirectPurchaseInvoicesDestroy,
    },
  },
  "pro-purchase-inv": {
    type: "pro_purchase_invoice",
    sourcedLines: true,
    supplierPicker: true,
    managePermission: "pro_purchase_invoices.manage",
    client: {
      index: invoicesProPurchaseInvoicesIndex,
      show: invoicesProPurchaseInvoicesShow,
      store: invoicesProPurchaseInvoicesStore,
      update: invoicesProPurchaseInvoicesUpdate,
      status: invoicesProPurchaseInvoicesStatus,
      destroy: invoicesProPurchaseInvoicesDestroy,
    },
  },
};
