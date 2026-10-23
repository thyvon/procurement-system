"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import Cropper from "react-easy-crop";
import type { Area } from "react-easy-crop";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const OUTPUT_WIDTH = 600;
const OUTPUT_HEIGHT = 300;

type Props = {
  imageUrl: string;
  onOpenChange: (open: boolean) => void;
  onConfirm: (file: File) => void;
};

async function cropToPng(imageUrl: string, area: Area): Promise<File> {
  const image = await new Promise<HTMLImageElement>((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error("failed to load image"));
    img.src = imageUrl;
  });

  const canvas = document.createElement("canvas");
  canvas.width = OUTPUT_WIDTH;
  canvas.height = OUTPUT_HEIGHT;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("canvas is not supported");
  ctx.drawImage(
    image,
    area.x,
    area.y,
    area.width,
    area.height,
    0,
    0,
    OUTPUT_WIDTH,
    OUTPUT_HEIGHT
  );

  const blob = await new Promise<Blob | null>((resolve) =>
    canvas.toBlob(resolve, "image/png")
  );
  if (!blob) throw new Error("failed to encode signature");
  return new File([blob], "signature.png", { type: "image/png" });
}

export function SignatureCropDialog({
  imageUrl,
  onOpenChange,
  onConfirm,
}: Props) {
  const t = useTranslations("profile");
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [areaPixels, setAreaPixels] = useState<Area | null>(null);
  const [cropping, setCropping] = useState(false);

  const handleConfirm = async () => {
    if (!areaPixels) return;
    setCropping(true);
    try {
      onConfirm(await cropToPng(imageUrl, areaPixels));
    } catch {
      toast.error(t("cropFailed"));
    } finally {
      setCropping(false);
    }
  };

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t("cropTitle")}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <div className="relative h-64 overflow-hidden rounded-md border border-border bg-muted">
            <Cropper
              image={imageUrl}
              crop={crop}
              zoom={zoom}
              aspect={2}
              onCropChange={setCrop}
              onZoomChange={setZoom}
              onCropComplete={(_, croppedAreaPixels) =>
                setAreaPixels(croppedAreaPixels)
              }
            />
          </div>
          <div className="flex items-center gap-3">
            <Label htmlFor="signature-zoom" className="shrink-0">
              {t("cropZoom")}
            </Label>
            <Slider
              id="signature-zoom"
              min={1}
              max={3}
              step={0.1}
              value={[zoom]}
              onValueChange={(value) =>
                setZoom(typeof value === "number" ? value : value[0])
              }
              className="flex-1"
            />
          </div>
        </div>
        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={cropping}
          >
            {t("cancel")}
          </Button>
          <Button onClick={handleConfirm} disabled={!areaPixels || cropping}>
            {cropping ? t("saving") : t("cropApply")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
