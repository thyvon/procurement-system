<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\RefreshToken;

class AuthService
{
    public function issuePair(User $user): array
    {
        $accessToken = $user->createToken(
            'access',
            expiresAt: now()->addMinutes(config('auth.access_ttl_minutes', 15)),
        );

        [$refreshToken] = $this->issueRefreshToken($user, (string) Str::ulid());

        return [
            'access_token' => $accessToken->plainTextToken,
            'expires_in' => config('auth.access_ttl_minutes', 15) * 60,
            'refresh_token' => $refreshToken,
        ];
    }

    public function rotate(string $plaintext): array
    {
        $hash = hash('sha256', $plaintext);

        /** @var RefreshToken|null $token */
        $token = RefreshToken::query()
            ->where('token_hash', $hash)
            ->first();

        if ($token === null) {
            throw new InvalidRefreshTokenException('Invalid refresh token.');
        }

        if ($token->isRevoked()) {
            $this->revokeFamilyByRoot($token);

            throw new InvalidRefreshTokenException('Refresh token reuse detected. All sessions in this family have been revoked.');
        }

        if ($token->rotated_at !== null) {
            $this->revokeFamilyByRoot($token);

            throw new InvalidRefreshTokenException('Refresh token reuse detected. All sessions in this family have been revoked.');
        }

        if ($token->isExpired()) {
            throw new InvalidRefreshTokenException('Refresh token expired.');
        }

        return DB::transaction(function () use ($token) {
            [$newPlain, $successorId] = $this->issueRefreshToken($token->user, $token->family_id);

            $token->forceFill([
                'rotated_at' => now(),
                'rotated_to' => $successorId,
            ])->save();

            return [
                'access_token' => $token->user->createToken(
                    'access',
                    expiresAt: now()->addMinutes(config('auth.access_ttl_minutes', 15)),
                )->plainTextToken,
                'expires_in' => config('auth.access_ttl_minutes', 15) * 60,
                'refresh_token' => $newPlain,
            ];
        });
    }

    private function revokeFamilyByRoot(RefreshToken $token): void
    {
        DB::transaction(function () use ($token) {
            RefreshToken::query()
                ->where('family_id', $token->family_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });
    }

    public function revokeFamily(User $user): void
    {
        DB::transaction(function () use ($user) {
            $latest = RefreshToken::query()
                ->where('user_id', $user->getKey())
                ->orderByDesc('created_at')
                ->first();

            if ($latest !== null) {
                RefreshToken::query()
                    ->where('family_id', $latest->family_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            $user->tokens()->delete();
        });
    }

    private function issueRefreshToken(User $user, string $familyId): array
    {
        $plaintext = Str::random(64);
        $id = (string) Str::ulid();

        $token = new RefreshToken([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $plaintext),
            'family_id' => $familyId,
            'expires_at' => now()->addDays(config('auth.refresh_ttl_days', 14)),
        ]);

        $token->id = $id;
        $token->save();

        return [$plaintext, $id];
    }
}
