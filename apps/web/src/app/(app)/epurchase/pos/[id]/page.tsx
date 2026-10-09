import { EPurchasePoDetail } from "@/features/epurchase/epurchase-pos-detail";

export default async function EPurchasePoDetailRoute({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ ref?: string | string[] }>;
}) {
  const { id } = await params;
  const { ref } = await searchParams;
  const refNum = typeof ref === "string" ? ref : null;
  return <EPurchasePoDetail poId={id} refNum={refNum} />;
}
