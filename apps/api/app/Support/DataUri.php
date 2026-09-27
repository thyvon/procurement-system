<?php

namespace App\Support;

/**
 * Shared data-URI decoding for company-provided images (avatars, signatures).
 *
 * The E-Purchase profile endpoints hand us images as `data:image/...;base64,...`
 * strings; both image services apply the same validation rules before anything
 * touches the disk.
 */
final class DataUri
{
    /**
     * Decode a base64 image data-URI to raw bytes, or null when the payload
     * is not an image data-URI, fails to decode, or exceeds $maxBytes.
     */
    public static function decode(string $dataUri, int $maxBytes): ?string
    {
        $comma = strpos($dataUri, ',');

        if ($comma === false) {
            return null;
        }

        $meta = substr($dataUri, 0, $comma);

        if (! str_starts_with($meta, 'data:image/') || ! str_contains($meta, ';base64')) {
            return null;
        }

        $bytes = base64_decode(substr($dataUri, $comma + 1), true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > $maxBytes) {
            return null;
        }

        return $bytes;
    }

    /**
     * Detect the MIME type of raw image bytes, or null when unreadable.
     */
    public static function sniffMime(string $bytes): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        try {
            $mime = finfo_buffer($finfo, $bytes);

            return is_string($mime) ? $mime : null;
        } finally {
            finfo_close($finfo);
        }
    }
}
