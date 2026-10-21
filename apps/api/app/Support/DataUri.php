<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Shared data-URI helpers for company-provided images (avatars, signatures,
 * logos).
 *
 * The E-Purchase profile endpoints hand us images as `data:image/...;base64,...`
 * strings; both image services apply the same validation rules before anything
 * touches the disk. The print sheets go the other way: `fromPublic()` folds a
 * public-disk image back into a data-URI so the PDF renderer never fetches
 * assets over the network.
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
     * Encode raw image bytes as a `data:` URI (the inverse of decode()).
     */
    public static function encode(string $bytes, string $mime): string
    {
        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /**
     * Resolve an image reference on the public disk to an inlinable data-URI.
     *
     * Accepts what the API hands the frontend — `Storage::url()` absolute
     * URLs (`http://app/storage/x.png`) — as well as storage-relative paths
     * (`signatures/x.png`). Already-encoded data-URIs pass through. Returns
     * null when the value is empty, points outside the public disk, or the
     * file is gone; callers then keep their original value.
     */
    public static function fromPublic(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:')) {
            return $value;
        }

        $path = $value;

        if (str_contains($value, '://')) {
            $base = rtrim(Storage::disk('public')->url(''), '/').'/';

            if (! str_starts_with($value, $base)) {
                return null;
            }

            $path = substr($value, strlen($base));
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $bytes = $disk->get($path);

        if ($bytes === '') {
            return null;
        }

        return self::encode($bytes, self::sniffMime($bytes) ?? 'image/png');
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
