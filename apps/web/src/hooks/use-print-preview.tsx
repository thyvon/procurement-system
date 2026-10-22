"use client";

import { useCallback, useEffect, useRef, useState, type ReactNode } from "react";
import { useMutation } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Download, Loader2, Printer } from "lucide-react";
import { fetchPrintPdf } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

/**
 * Hidden iframe reused for every print: Chrome/Firefox print an embedded PDF
 * through its frame — `window.print()` on the page itself would print the
 * app instead of the document. Reusing one frame means repeating the print
 * click just re-prints the same blob (blob URLs are absolute, so `src`
 * equality tells us no reload is needed).
 */
let printFrame: HTMLIFrameElement | null = null;

function printPdf(src: string): void {
  if (!printFrame) {
    printFrame = document.createElement("iframe");
    printFrame.setAttribute("aria-hidden", "true");
    printFrame.style.display = "none";
    document.body.appendChild(printFrame);
  }

  const run = () => {
    printFrame?.focus();
    printFrame?.contentWindow?.print();
  };

  if (printFrame.src === src) {
    run();
    return;
  }
  printFrame.onload = run;
  printFrame.src = src;
}

export type PrintPreview = {
  /** Opens the preview dialog and fetches the PDF behind `url`. */
  open: (url: string, documentCode?: string) => void;
  /** True while the PDF is fetching — keep the trigger disabled. */
  isPending: boolean;
  /** Render once next to the page's other dialogs. */
  dialog: ReactNode;
};

/**
 * In-app print dialog: fetches the server-rendered PDF (`GET …/print`) and
 * shows it in a native iframe viewer inside a shadcn Dialog, with download
 * and print actions. Fetch failures throw `ApiError`; the global
 * MutationCache toasts the API message and the dialog closes itself.
 */
export function usePrintPreview(): PrintPreview {
  const t = useTranslations("printPreview");
  const tc = useTranslations("common");
  const [url, setUrl] = useState<string | null>(null);
  const [filename, setFilename] = useState("document.pdf");
  // The iframe stays mounted while loading; the preloader covers it until
  // the built-in PDF viewer fires its load event.
  const [frameReady, setFrameReady] = useState(false);
  // Latest blob URL; revocation happens when it is replaced, when the dialog
  // closes, and when the hook unmounts.
  const objectUrlRef = useRef<string | null>(null);

  const mutation = useMutation({
    mutationFn: async (printUrl: string) => {
      const blob = await fetchPrintPdf(printUrl);
      const next = URL.createObjectURL(blob);
      if (objectUrlRef.current) {
        URL.revokeObjectURL(objectUrlRef.current);
      }
      objectUrlRef.current = next;
      return next;
    },
    // A failed fetch drops the dialog; MutationCache already showed the toast.
    onError: () => setUrl(null),
  });

  const objectUrl = mutation.data;

  useEffect(
    () => () => {
      if (objectUrlRef.current) {
        URL.revokeObjectURL(objectUrlRef.current);
        objectUrlRef.current = null;
      }
    },
    [],
  );

  // Safety net: if a browser never fires the PDF iframe's load event, drop
  // the preloader anyway instead of covering a finished viewer forever.
  useEffect(() => {
    if (!objectUrl) {
      return;
    }
    const fallback = window.setTimeout(() => setFrameReady(true), 4000);
    return () => window.clearTimeout(fallback);
  }, [objectUrl]);

  const open = useCallback(
    (nextUrl: string, documentCode?: string) => {
      mutation.reset();
      setUrl(nextUrl);
      setFrameReady(false);
      setFilename(documentCode ? `${documentCode}.pdf` : "document.pdf");
      mutation.mutate(nextUrl);
    },
    [mutation],
  );

  const close = useCallback(() => {
    setUrl(null);
    mutation.reset();
    if (objectUrlRef.current) {
      URL.revokeObjectURL(objectUrlRef.current);
      objectUrlRef.current = null;
    }
  }, [mutation]);

  const handleDownload = () => {
    if (!objectUrl) {
      return;
    }
    const anchor = document.createElement("a");
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.click();
  };

  const handlePrint = () => {
    if (objectUrl) {
      printPdf(objectUrl);
    }
  };

  const dialog = (
    <Dialog
      open={url !== null}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          close();
        }
      }}
    >
      <DialogContent className="sm:max-w-4xl">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
          <DialogDescription>{t("description")}</DialogDescription>
        </DialogHeader>
        <div className="relative h-[70vh] w-full">
          {objectUrl ? (
            <iframe
              src={objectUrl}
              title={t("title")}
              onLoad={() => setFrameReady(true)}
              className={cn(
                "h-full w-full rounded-md border",
                frameReady ? "visible" : "invisible",
              )}
            />
          ) : null}
          {!frameReady ? (
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-md border">
              <Loader2 className="size-6 animate-spin text-muted-foreground" />
              <p className="text-sm text-muted-foreground">
                {tc("loading")}
              </p>
            </div>
          ) : null}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={close}>
            {t("close")}
          </Button>
          <Button
            variant="outline"
            onClick={handleDownload}
            disabled={!objectUrl}
          >
            <Download />
            {t("download")}
          </Button>
          <Button onClick={handlePrint} disabled={!objectUrl}>
            <Printer />
            {t("print")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );

  return { open, isPending: mutation.isPending, dialog };
}
