/**
 * Breadcrumb registry — the single standard for every page.
 *
 * One entry per route pattern, one label key per URL segment, in order.
 * A `null` label means the segment exists in the URL but never renders
 * (record IDs). Labels are i18n keys: `nav.*` or `breadcrumb.*`.
 *
 * Adding a page = adding one line here; the bar only renders what this
 * file resolves.
 */
export const ROUTE_CRUMBS: Record<string, (string | null)[]> = {
  "/epurchase": ["nav.epurchase"],
  "/epurchase/items": ["nav.epurchase", "nav.epurchaseItems"],
  "/epurchase/suppliers": ["nav.epurchase", "nav.epurchaseSuppliers"],
  "/products": ["nav.products"],
  "/products/categories": ["nav.products", "nav.categories"],
  "/products/brands": ["nav.products", "nav.brands"],
  "/products/groups": ["nav.products", "nav.groups"],
  "/products/uoms": ["nav.products", "nav.uoms"],
  "/products/variation-templates": ["nav.products", "nav.variationTemplates"],
  "/products/create": ["nav.products", "breadcrumb.create"],
  "/products/[id]": ["nav.products", "breadcrumb.details"],
  "/products/[id]/edit": ["nav.products", null, "breadcrumb.edit"],
  "/purchase-orders/list": ["nav.purchaseOrders", null],
  "/purchase-orders/evaluations": ["nav.purchaseOrders", "nav.evaluations"],
  "/purchase-orders/evaluations/new": [
    "nav.purchaseOrders",
    "breadcrumb.evaluation",
    "breadcrumb.create",
  ],
  "/purchase-orders/evaluations/[id]": [
    "nav.purchaseOrders",
    "breadcrumb.evaluation",
    "breadcrumb.details",
  ],
  "/purchase-orders/evaluations/[id]/edit": [
    "nav.purchaseOrders",
    "breadcrumb.evaluation",
    null,
    "breadcrumb.edit",
  ],
  "/approvals": ["nav.approvals"],
  "/approvals/[id]": ["nav.approvals", "breadcrumb.details"],
  "/users": ["nav.users"],
  "/users/roles": ["nav.users", "nav.roles"],
}

/** Used only for routes not yet registered in ROUTE_CRUMBS. */
const SEGMENT_FALLBACKS: Record<string, string> = {
  products: "nav.products",
  epurchase: "nav.epurchase",
  items: "nav.epurchaseItems",
  suppliers: "nav.epurchaseSuppliers",
  "purchase-orders": "nav.purchaseOrders",
  evaluations: "nav.evaluations",
  approvals: "nav.approvals",
  users: "nav.users",
  list: "breadcrumb.list",
  new: "breadcrumb.create",
  create: "breadcrumb.create",
  edit: "breadcrumb.edit",
}

const RECORD_ID_PATTERNS = [
  /^[0-9A-HJKMNP-TV-Z]{26}$/i,
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
]

function isRecordId(segment: string): boolean {
  return RECORD_ID_PATTERNS.some((pattern) => pattern.test(segment))
}

function matchPattern(segments: string[]): string | null {
  let best: string | null = null
  let bestScore = -1

  for (const [pattern, labels] of Object.entries(ROUTE_CRUMBS)) {
    const parts = pattern.split("/").filter(Boolean)
    if (parts.length !== segments.length || labels.length === 0) continue

    let score = 0
    let matches = true

    for (let i = 0; i < parts.length; i++) {
      if (parts[i].startsWith("[")) continue
      if (parts[i] !== segments[i]) {
        matches = false
        break
      }
      score++
    }

    // Literal segments win over `[param]` patterns so `/evaluations/new`
    // never falls into `/evaluations/[id]`.
    if (matches && score > bestScore) {
      best = pattern
      bestScore = score
    }
  }

  return best
}

function fallbackLabel(segment: string): string | null {
  if (isRecordId(segment)) return "breadcrumb.details"
  return SEGMENT_FALLBACKS[segment] ?? segment
}

export type BreadcrumbCrumb = {
  labelKey: string
  href: string
  isCurrent: boolean
}

/** Resolves a pathname into renderable crumbs; record IDs never surface. */
export function resolveBreadcrumbs(pathname: string): BreadcrumbCrumb[] {
  const segments = pathname.split("/").filter(Boolean)
  if (segments.length === 0) return []

  const pattern = matchPattern(segments)
  const labels = pattern
    ? ROUTE_CRUMBS[pattern]
    : segments.map((segment) => fallbackLabel(segment))

  const crumbs = segments
    .map((segment, index) => ({
      labelKey:
        labels[index] === undefined ? fallbackLabel(segment) : labels[index],
      href: "/" + segments.slice(0, index + 1).join("/"),
    }))
    .filter((crumb): crumb is { labelKey: string; href: string } =>
      crumb.labelKey !== null
    )

  return crumbs.map((crumb, index) => ({
    ...crumb,
    isCurrent: index === crumbs.length - 1,
  }))
}
