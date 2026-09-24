"use client";

import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ArrowLeft, Pencil } from "lucide-react";
import { unwrap, withAuth } from "@/lib/api-client";
import { productsItemsShow } from "@/lib/api/product/product";
import type { ProductsItemsShow200Data } from "@/lib/api/model/productsItemsShow200Data";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-3 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className="text-right font-medium">{value ?? "—"}</span>
    </div>
  );
}

export function ProductDetail({ productId }: { productId: string }) {
  const router = useRouter();
  const t = useTranslations("products.detail");
  const tt = useTranslations("products.table");
  const tc = useTranslations("products.columns");

  const query = useQuery({
    queryKey: ["products", productId],
    queryFn: async () =>
      unwrap<ProductsItemsShow200Data>(
        await productsItemsShow(productId, withAuth())
      ),
  });

  if (query.isPending) {
    return null;
  }

  if (query.isError || !query.data) {
    return (
      <div className="space-y-4">
        <p className="text-sm text-muted-foreground">{t("notFound")}</p>
        <Button variant="outline" onClick={() => router.push("/products")}>
          <ArrowLeft />
          {t("back")}
        </Button>
      </div>
    );
  }

  const product = query.data;
  const variants = product.variants ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={() => router.push("/products")}
            aria-label={t("back")}
          >
            <ArrowLeft />
          </Button>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-semibold tracking-tight">
                {product.name}
              </h1>
              <Badge variant={product.isActive ? "default" : "secondary"}>
                {product.isActive ? tt("active") : tt("inactive")}
              </Badge>
            </div>
            <p className="font-mono text-sm text-muted-foreground">
              {product.code}
            </p>
          </div>
        </div>
        <Button onClick={() => router.push(`/products/${product.id}/edit`)}>
          <Pencil />
          {t("edit")}
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("description")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <Row label={tc("nameKm")} value={product.nameKm} />
            <div className="text-sm">
              <span className="text-muted-foreground">{t("description")}</span>
              <p className="mt-1 whitespace-pre-wrap">
                {product.description || "—"}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("catalog")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <Row label={t("type")} value={product.productType} />
            <Row label={t("category")} value={product.categoryName} />
            <Row label={t("group")} value={product.groupName} />
            <Row label={t("brand")} value={product.brandName} />
            <Row label={t("uom")} value={product.uomShortName} />
            <Row label={t("subUnit")} value={product.subUnitId} />
            <Row
              label={t("purchasePrice")}
              value={
                product.purchasePrice !== null
                  ? `$${Number(product.purchasePrice).toFixed(2)}`
                  : null
              }
            />
            <Row
              label={t("subUnitPurchasePrice")}
              value={
                product.subUnitPurchasePrice !== null
                  ? `$${Number(product.subUnitPurchasePrice).toFixed(2)}`
                  : null
              }
            />
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("variants")}</CardTitle>
        </CardHeader>
        <CardContent>
          {variants.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("noVariants")}</p>
          ) : (
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="bg-muted/60">
                  <tr>
                    <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                      {t("code")}
                    </th>
                    <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                      {tc("name")}
                    </th>
                    <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                      {t("purchasePrice")}
                    </th>
                    <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                      {t("subUnitPurchasePrice")}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {variants.map((v) => (
                    <tr key={v.id} className="border-t border-border">
                      <td className="px-3 py-2 font-mono text-xs">
                        {v.code ?? "—"}
                      </td>
                      <td className="px-3 py-2">{v.name}</td>
                      <td className="px-3 py-2 font-mono">
                        {v.purchasePrice !== null
                          ? `$${Number(v.purchasePrice).toFixed(2)}`
                          : "—"}
                      </td>
                      <td className="px-3 py-2 font-mono">
                        {v.subUnitPurchasePrice !== null
                          ? `$${Number(v.subUnitPurchasePrice).toFixed(2)}`
                          : "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
