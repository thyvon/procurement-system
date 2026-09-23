<?php

namespace Modules\Users\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarService
{
    private const DISK = 'public';

    private const DIRECTORY = 'avatars';

    private const MAX_BYTES = 2_048 * 1024;

    /**
     * MIME types accepted for avatars, mapped to file extensions.
     *
     * @var array<string, string>
     */
    private const ALLOWED_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function replaceWithUpload(User $user, UploadedFile $file): void
    {
        $ext = self::ALLOWED_MIME_EXT[$file->getMimeType() ?? ''] ?? 'jpg';
        $name = sprintf('user_%d_%s.%s', $user->getKey(), Str::lower(Str::random(12)), $ext);

        $path = $file->storeAs(self::DIRECTORY, $name, self::DISK);

        $this->swap($user, $path);
    }

    /**
     * Apply the company (E-Purchase) photo unless the user uploaded their own.
     *
     * Invalid or oversized payloads are skipped silently: the photo is optional
     * garnish and must never break login.
     */
    public function applyCompanyPhoto(User $user, string $dataUri): void
    {
        $bytes = $this->decodeDataUri($dataUri);

        if ($bytes === null) {
            return;
        }

        $current = $user->avatar_path;

        if ($current !== null && str_starts_with(basename($current), 'user_')) {
            return;
        }

        if ($current !== null
            && str_starts_with(basename($current), 'sso_')
            && Storage::disk(self::DISK)->exists($current)
            && hash('sha256', Storage::disk(self::DISK)->get($current)) === hash('sha256', $bytes)) {
            return;
        }

        $mime = $this->sniffMime($bytes);
        $ext = $mime !== null ? (self::ALLOWED_MIME_EXT[$mime] ?? null) : null;

        if ($ext === null) {
            return;
        }

        $name = sprintf('sso_%d_%s.%s', $user->getKey(), Str::lower(Str::random(12)), $ext);
        $path = self::DIRECTORY.'/'.$name;

        Storage::disk(self::DISK)->put($path, $bytes);

        $this->swap($user, $path);
    }

    private function swap(User $user, string $path): void
    {
        $previous = $user->avatar_path;

        $user->update(['avatar_path' => $path]);

        if ($previous !== null
            && Storage::disk(self::DISK)->exists($previous)
            && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }

    private function decodeDataUri(string $dataUri): ?string
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

        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        return $bytes;
    }

    private function sniffMime(string $bytes): ?string
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
