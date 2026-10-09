import { EPurchasePrDetail } from "@/features/epurchase/epurchase-pr-detail";

export default async function EPurchasePrDetailRoute({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ ref?: string | string[] }>;
}) {
  const { id } = await params;
  const { ref } = await searchParams;
  const refNum = typeof ref === "string" ? ref : null;
  return <EPurchasePrDetail prId={id} refNum={refNum} />;
}
