<?php

namespace App\Support;

/**
 * Loads the built print sheet stylesheet (`resources/css/print.css` →
 * `public/build`) as raw CSS so the print services can inline it into the PDF
 * HTML. The renderer must never fetch assets over the network: a self-fetch
 * can deadlock the single-worker dev server, and a Gotenberg container cannot
 * reach `APP_URL`.
 *
 * Returns an empty string when the build manifest or the entry is missing —
 * tests and fresh checkouts then render unstyled instead of failing; run
 * `npm run build` in apps/api for a styled sheet.
 */
final class PrintStylesheet
{
    private const ENTRY = 'resources/css/print.css';

    private static ?string $css = null;

    public static function load(): string
    {
        if (self::$css !== null) {
            return self::$css;
        }

        try {
            $manifestPath = public_path('build/manifest.json');

            if (! is_file($manifestPath)) {
                return self::$css = '';
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (! is_array($manifest)) {
                return self::$css = '';
            }

            $file = $manifest[self::ENTRY]['file'] ?? null;

            if (! is_string($file)) {
                return self::$css = '';
            }

            $path = public_path('build/'.ltrim($file, '/'));

            return self::$css = is_file($path) ? (string) file_get_contents($path) : '';
        } catch (\Throwable) {
            return self::$css = '';
        }
    }
}
