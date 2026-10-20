"use client";

import { useRouter } from "next/navigation";
import { AdvanceLiquidationForm } from "./advance-liquidation-form";

interface AdvanceLiquidationFormPageProps {
  liquidationId?: string;
}

export function AdvanceLiquidationFormPage({
  liquidationId,
}: AdvanceLiquidationFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <AdvanceLiquidationForm
        liquidationId={liquidationId}
        onBack={() => router.push("/payments/advance-liquidations")}
      />
    </div>
  );
}
