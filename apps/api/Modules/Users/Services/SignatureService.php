<?php

namespace Modules\Users\Services;

use App\Models\User;
use App\Support\DataUri;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignatureService
{
    private const DISK = 'public';

    private const DIRECTORY = 'signatures';

    private const MAX_BYTES = 2_048 * 1024;

    /**
     * MIME types accepted for signatures, mapped to file extensions.
     *
     * @var array<string, string>
     */
    private const ALLOWED_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Apply the company (E-Purchase) signature.
     *
     * Invalid or oversized payloads are skipped silently: the signature is
     * optional garnish and must never break login.
     *
     * Previous files are deliberately kept (unlike avatars): approval
     * snapshots freeze signature_path at submit time, so historical
     * documents must keep resolving the file they were printed with.
     * Identical bytes reuse the existing path, so in practice a user only
     * ever gains a second file when their signature actually changes.
     */
    public function applyCompanySignature(User $user, string $dataUri): void
    {
        $bytes = DataUri::decode($dataUri, self::MAX_BYTES);

        if ($bytes === null) {
            return;
        }

        $current = $user->signature_path;

        if ($current !== null
            && Storage::disk(self::DISK)->exists($current)
            && hash('sha256', Storage::disk(self::DISK)->get($current)) === hash('sha256', $bytes)) {
            return;
        }

        $mime = DataUri::sniffMime($bytes);
        $ext = $mime !== null ? (self::ALLOWED_MIME_EXT[$mime] ?? null) : null;

        if ($ext === null) {
            return;
        }

        $name = sprintf('sso_%d_%s.%s', $user->getKey(), Str::lower(Str::random(12)), $ext);
        $path = self::DIRECTORY.'/'.$name;

        Storage::disk(self::DISK)->put($path, $bytes);

        $user->update(['signature_path' => $path]);
    }
}
