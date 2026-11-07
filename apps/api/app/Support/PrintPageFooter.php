<?php

namespace App\Support;

use Spatie\LaravelPdf\PdfBuilder;

/**
 * "Page X of Y" footer for the server-rendered sheets. Chromium stamps the
 * template on every page and cannot skip a lone page, so the sheet is
 * rendered once to measure it and the footer is attached only when the
 * document spans more than one page. The measure render is kept by
 * PdfBuilder's cache, so only multi-page sheets ever render twice.
 */
final class PrintPageFooter
{
    /**
     * An explicit invisible header keeps Chromium from filling the header
     * slot with its default date/title/url line when only a footer is wanted.
     */
    private const BLANK_HEADER = '<div style="font-size:0"></div>';

    private const FOOTER = '<div style="width:100%; text-align:center; font-size:9px; color:#000; font-family:Arial,sans-serif;">'
        .'Page <span class="pageNumber"></span> of <span class="totalPages"></span>'
        .'</div>';

    public static function attach(PdfBuilder $pdf): void
    {
        if (self::pageCount($pdf->generatePdfContent()) <= 1) {
            return;
        }

        $pdf->headerHtml(self::BLANK_HEADER)->footerHtml(self::FOOTER);
    }

    /**
     * Leaf pages of a rendered PDF. Chromium leaves the page-tree
     * dictionaries uncompressed, so the raw bytes carry one `/Type /Page`
     * per page while the `/Type /Pages` container must not count.
     * Unreadable input degrades to a single page — the footer stays off.
     */
    public static function pageCount(string $pdf): int
    {
        preg_match_all('~/Type\s*/Page(?!s)~', $pdf, $matches);

        return max(count($matches[0]), 1);
    }
}
