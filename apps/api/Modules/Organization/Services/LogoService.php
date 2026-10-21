<?php

namespace Modules\Organization\Services;

use App\Models\User;
use App\Support\DataUri;
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

    /**
     * The logo a print sheet carries: the authed user's entity logo — what
     * the web sheets read through `useEntity()` — inlined as a data-URI so
     * the PDF renderer never fetches it over the network. Null when the user
     * has no entity, no logo, or the file cannot be read locally.
     */
    public function forUser(User $user): ?string
    {
        if ($user->entity_id === null) {
            return null;
        }

        $path = Entity::query()->whereKey($user->entity_id)->value('logo_path');

        return is_string($path) ? DataUri::fromPublic($path) : null;
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
