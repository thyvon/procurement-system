<?php

namespace Modules\Organization\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Organization\Models\Entity;

class LogoService
{
    private const DISK = 'public';

    private const DIRECTORY = 'logos';

    /**
     * MIME types accepted for logos, mapped to file extensions.
     *
     * @var array<string, string>
     */
    private const ALLOWED_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function replaceWithUpload(Entity $entity, UploadedFile $file, User $user): void
    {
        $ext = self::ALLOWED_MIME_EXT[$file->getMimeType() ?? ''] ?? 'jpg';
        $name = sprintf('entity_%s_%s.%s', $entity->getKey(), Str::lower(Str::random(12)), $ext);

        $path = $file->storeAs(self::DIRECTORY, $name, self::DISK);

        $this->swap($entity, $path, $user);
    }

    private function swap(Entity $entity, string $path, User $user): void
    {
        $previous = $entity->logo_path;

        $entity->update([
            'logo_path' => $path,
            'updated_by' => $user->getKey(),
        ]);

        if ($previous !== null
            && Storage::disk(self::DISK)->exists($previous)
            && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }
}
