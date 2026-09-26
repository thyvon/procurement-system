<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\EPurchase\Services\EPurchaseClient;
use Modules\EPurchase\Services\EPurchaseLoginResult;
use Modules\EPurchase\Services\EPurchaseSession;
use Modules\EPurchase\Services\EPurchaseSessionService;
use Modules\EPurchase\Services\EPurchaseUnavailableException;
use Modules\EPurchase\Services\InvalidCompanyCredentialsException;
use Modules\Organization\Models\Entity;
use Modules\Users\Services\AvatarService;
use Spatie\Permission\Models\Role;

class CompanyLoginService
{
    public function __construct(
        private readonly EPurchaseClient $company,
        private readonly AuthService $auth,
        private readonly AvatarService $avatars,
        private readonly EPurchaseSessionService $sessions,
    ) {}

    /**
     * Relay company credentials, provision the local user, and issue our own token pair.
     *
     * The company password is never stored; the company JWT never leaves this method.
     *
     * @return array{user: User, tokens: array{access_token: string, expires_in: int, refresh_token: string}}
     *
     * @throws InvalidCompanyCredentialsException
     * @throws EPurchaseUnavailableException
     */
    public function login(string $employeeId, string $password): array
    {
        $result = $this->company->login($employeeId, $password);

        $user = $this->provisionUser($result);

        if ($result->userPhoto !== null) {
            $this->avatars->applyCompanyPhoto($user, $result->userPhoto);
        }

        $this->sessions->put($user->getAuthIdentifier(), new EPurchaseSession(
            jwt: $result->jwt,
            formToken: $result->formToken,
            cookieHeader: implode('; ', $result->cookies),
            expiresAt: time() + max(60, (int) config('epurchase.session_ttl', 1800)),
        ));

        return [
            'user' => $user,
            'tokens' => $this->auth->issuePair($user),
        ];
    }

    private function provisionUser(EPurchaseLoginResult $result): User
    {
        return DB::transaction(function () use ($result): User {
            /** @var User|null $user */
            $user = User::withTrashed()->where('email', $result->email)->first();

            if ($user !== null && $user->trashed()) {
                throw new InvalidCompanyCredentialsException('Local account has been deactivated.');
            }

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $result->name,
                    'email' => $result->email,
                    'password' => Str::password(32),
                    'entity_id' => $this->defaultEntityId(),
                    'position' => $result->position,
                ]);

                $user->assignRole(Role::findOrCreate('staff', 'sanctum'));

                return $user;
            }

            $changes = [];

            if ($user->name !== $result->name) {
                $changes['name'] = $result->name;
            }

            // The company system is the source of truth for the position, but a
            // login payload without one must never wipe what we already store.
            if ($result->position !== null && $user->position !== $result->position) {
                $changes['position'] = $result->position;
            }

            if ($changes !== []) {
                $user->update($changes);
            }

            return $user;
        });
    }

    private function defaultEntityId(): ?string
    {
        $code = config('epurchase.default_entity');

        if (! is_string($code) || $code === '') {
            return null;
        }

        $entityId = Entity::query()->where('code', $code)->value('id');

        return is_string($entityId) ? $entityId : null;
    }
}
