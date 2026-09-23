<?php

namespace Modules\EPurchase\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class EPurchaseClient
{
    /**
     * Exchange company credentials for a company session and profile.
     *
     * The company JWT never leaves the server: callers receive only the
     * normalized name/email and optional profile photo needed for local
     * provisioning.
     *
     * @throws InvalidCompanyCredentialsException when the company system rejects the credentials
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function login(string $employeeId, string $password): EPurchaseLoginResult
    {
        $baseUrl = config('epurchase.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new EPurchaseUnavailableException('E-Purchase is not configured.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout((int) config('epurchase.timeout', 10))
                ->connectTimeout(5)
                ->retry(
                    [100, 500],
                    when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    throw: false,
                )
                ->post((string) config('epurchase.login_path', '/default_user_access/login'), [
                    'login_with_db' => false,
                    'employee_id' => $employeeId,
                    'password' => $password,
                    'is_change_password' => 0,
                ]);
        } catch (ConnectionException $exception) {
            throw new EPurchaseUnavailableException('E-Purchase is unreachable.', 0, $exception);
        }

        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('result', $body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        if ($body['result'] !== 'success') {
            throw new InvalidCompanyCredentialsException('Invalid credentials.');
        }

        $companyUser = $body['user'] ?? null;
        $email = is_array($companyUser) ? ($companyUser['email'] ?? null) : null;
        $name = is_array($companyUser) ? ($companyUser['name'] ?? null) : null;
        $jwt = $body['data'] ?? null;
        $userPhoto = $body['userPhoto'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($name) || $name === '' || ! is_string($jwt) || $jwt === '') {
            throw new EPurchaseUnavailableException('E-Purchase returned an incomplete login payload.');
        }

        return new EPurchaseLoginResult(
            jwt: $jwt,
            name: $name,
            email: $email,
            userPhoto: is_string($userPhoto) && $userPhoto !== '' ? $userPhoto : null,
        );
    }
}
