<?php

namespace Modules\EPurchase\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class EPurchaseClient
{
    /**
     * Upstream paths are part of the E-Purchase API contract. base_url is the
     * host only (no /api): login is under /api, master lists are at root.
     */
    private const LOGIN_PATH = '/api/default_user_access/login';

    private const ITEMS_PATH = '/items-master-list';

    private const SUPPLIERS_PATH = '/suppliers-master-list';

    private const VENDOR_SEARCH_PATH = '/api/po/getVentors';

    private const VENDOR_INFO_PATH = '/api/po/getVendorInfo';

    /**
     * Exchange company credentials for a company session and profile.
     *
     * The company JWT never leaves the server: callers receive only the
     * normalized name/email and optional profile photo needed for local
     * provisioning, plus the form token/cookies needed to cache a session.
     *
     * @throws InvalidCompanyCredentialsException when the company system rejects the credentials
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function login(string $employeeId, string $password): EPurchaseLoginResult
    {
        $response = $this->send(
            null,
            fn (PendingRequest $http) => $http->post(self::LOGIN_PATH, [
                'login_with_db' => false,
                'employee_id' => $employeeId,
                'password' => $password,
                'is_change_password' => 0,
            ]),
        );

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
        $position = is_array($companyUser) ? ($companyUser['real_position'] ?? null) : null;
        $jwt = $body['data'] ?? null;
        $userPhoto = $body['userPhoto'] ?? null;
        $formToken = $body['formToken'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($name) || $name === '' || ! is_string($jwt) || $jwt === '') {
            throw new EPurchaseUnavailableException('E-Purchase returned an incomplete login payload.');
        }

        return new EPurchaseLoginResult(
            jwt: $jwt,
            name: $name,
            email: $email,
            userPhoto: is_string($userPhoto) && $userPhoto !== '' ? $userPhoto : null,
            formToken: is_string($formToken) && $formToken !== '' ? $formToken : null,
            position: is_string($position) && trim($position) !== '' ? trim($position) : null,
            cookies: $this->cookiePairs($response),
        );
    }

    /**
     * Fetch one page of company items (DataTables server protocol).
     *
     * @return array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>}
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function items(EPurchaseSession $session, int $start, int $length, string $search = ''): array
    {
        $result = $this->dataTablePage(
            $session,
            self::ITEMS_PATH,
            $start,
            $length,
            $search,
            $this->itemsColumnsQuery(),
        );

        $result['data'] = array_values(array_filter(
            $result['data'],
            fn ($row): bool => is_array($row)
                && (trim((string) ($row['ItemCode'] ?? '')) !== ''
                    || trim((string) ($row['Description'] ?? '')) !== '')
        ));

        return $result;
    }

    /**
     * Fetch one page of company suppliers (DataTables server protocol).
     * suppliers-master-list ignores columns[] — draw/start/length/search is enough.
     *
     * @return array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>}
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function suppliers(EPurchaseSession $session, int $start, int $length, string $search = ''): array
    {
        $result = $this->dataTablePage($session, self::SUPPLIERS_PATH, $start, $length, $search);

        $result['data'] = array_values(array_filter(
            $result['data'],
            fn ($row): bool => is_array($row)
                && (trim((string) ($row['code'] ?? '')) !== ''
                    || trim((string) ($row['company_name'] ?? '')) !== '')
        ));

        return $result;
    }

    /**
     * Search company vendors by free-text term (autocomplete).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function vendorSearch(EPurchaseSession $session, string $term): array
    {
        $body = $this->vendorRequest($session, self::VENDOR_SEARCH_PATH, json: ['term' => $term]);
        $rows = is_array($body['data'] ?? null) ? $body['data'] : [];

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * Fetch one vendor's full profile by numeric id (upstream `SupplierCode`).
     *
     * @return array<string, mixed>
     *
     * @throws EPurchaseSessionExpiredException when the cached session is rejected upstream
     * @throws EPurchaseUnavailableException when the company system is unreachable or malformed
     */
    public function vendorInfo(EPurchaseSession $session, string $supplierCode): array
    {
        $body = $this->vendorRequest(
            $session,
            self::VENDOR_INFO_PATH,
            json: ['SupplierCode' => $supplierCode],
        );
        $data = $body['data'] ?? null;

        if (! is_array($data)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        return $data;
    }

    /**
     * Authenticated POST against /api/po/* endpoints ({result, msg, data} envelope).
     * Upstream routes (getVentors, getVendorInfo) only accept POST.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     *
     * @throws EPurchaseSessionExpiredException
     * @throws EPurchaseUnavailableException
     */
    private function vendorRequest(
        EPurchaseSession $session,
        string $path,
        array $json = [],
    ): array {
        $response = $this->send(
            $session,
            fn (PendingRequest $http) => $http->asJson()->post($path, $json),
        );

        if ($response->status() === 401 || $response->status() === 419) {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        if (! $response->successful()) {
            throw new EPurchaseUnavailableException(
                'E-Purchase vendor request failed with status '.$response->status().'.'
            );
        }

        $body = $response->json();

        if (! is_array($body) || ($body['result'] ?? null) !== 'success' || ! array_key_exists('data', $body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        return $body;
    }

    /**
     * Authenticated GET against an upstream DataTables endpoint.
     *
     * @param  array<string, string>  $extraQuery
     * @return array{recordsTotal: int, recordsFiltered: int, data: array<int, array<string, mixed>>}
     *
     * @throws EPurchaseSessionExpiredException
     * @throws EPurchaseUnavailableException
     */
    private function dataTablePage(
        EPurchaseSession $session,
        string $path,
        int $start,
        int $length,
        string $search,
        array $extraQuery = [],
    ): array {
        $response = $this->send(
            $session,
            fn (PendingRequest $http) => $http->get($path, array_merge([
                'draw' => '1',
                'start' => (string) $start,
                'length' => (string) $length,
                'search[value]' => $search,
                'search[regex]' => 'false',
                'order[0][column]' => '11',
                'order[0][dir]' => 'desc',
                'getTable' => '1',
                '_' => (string) (int) (microtime(true) * 1000),
                ...($session->formToken !== null && $session->formToken !== ''
                    ? ['_token' => $session->formToken]
                    : []),
            ], $extraQuery)),
        );

        if ($response->status() === 401 || $response->status() === 419) {
            throw new EPurchaseSessionExpiredException('Company session expired. Please log in again.');
        }

        if (! $response->successful()) {
            throw new EPurchaseUnavailableException(
                'E-Purchase list request failed with status '.$response->status().'.'
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        if (($body['success'] ?? null) === false) {
            throw new EPurchaseUnavailableException(
                is_string($body['message'] ?? null) && $body['message'] !== ''
                    ? $body['message']
                    : 'E-Purchase rejected the list request.'
            );
        }

        if (! array_key_exists('data', $body)) {
            throw new EPurchaseUnavailableException('E-Purchase returned an unexpected response.');
        }

        $rows = is_array($body['data']) ? $body['data'] : [];

        return [
            'recordsTotal' => (int) ($body['recordsTotal'] ?? 0),
            'recordsFiltered' => (int) ($body['recordsFiltered'] ?? count($rows)),
            'data' => array_values($rows),
        ];
    }

    /**
     * Shared HTTP bootstrap: base_url, timeout, retry policy, and optional
     * session headers (Bearer JWT + cookies). Connection failures map to
     * EPurchaseUnavailableException.
     *
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws EPurchaseUnavailableException
     */
    private function send(?EPurchaseSession $session, callable $send): Response
    {
        $baseUrl = config('epurchase.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new EPurchaseUnavailableException('E-Purchase is not configured.');
        }

        $http = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout((int) config('epurchase.timeout', 10))
            ->connectTimeout(5)
            ->retry(
                [100, 500],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );

        if ($session !== null) {
            $http = $http->withHeaders([
                'Authorization' => 'Bearer '.$session->jwt,
                'X-Requested-With' => 'XMLHttpRequest',
                ...($session->cookieHeader !== '' ? ['Cookie' => $session->cookieHeader] : []),
            ]);
        }

        try {
            return $send($http);
        } catch (ConnectionException $exception) {
            throw new EPurchaseUnavailableException('E-Purchase is unreachable.', 0, $exception);
        }
    }

    /**
     * @return array<int, string> name=value cookie pairs for the Cookie request header
     */
    private function cookiePairs(Response $response): array
    {
        $pairs = [];

        foreach ($response->cookies() as $cookie) {
            $pairs[] = $cookie->getName().'='.$cookie->getValue();
        }

        if ($pairs === []) {
            $setCookie = $response->header('Set-Cookie');
            if (is_string($setCookie) && $setCookie !== '') {
                foreach (explode("\n", $setCookie) as $line) {
                    $first = trim(explode(';', $line, 2)[0]);
                    if ($first !== '') {
                        $pairs[] = $first;
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, string>
     */
    private function itemsColumnsQuery(): array
    {
        $cols = [
            ['data' => 'image', 'name' => 'image', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'ItemCode', 'name' => 'ItemCode', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'Description', 'name' => 'Description', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'LongDescription', 'name' => 'LongDescription', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'BaseItemUnit', 'name' => 'BaseItemUnit', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'category', 'name' => 'category', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'sub_category', 'name' => 'sub_category', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'is_manage_price', 'name' => 'is_manage_price', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'estimate_price', 'name' => 'estimate_price', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'avg_price_3_months', 'name' => 'avg_price_3_months', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'remark', 'name' => 'remark', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'show_created_at', 'name' => 'created_at', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'created_by', 'name' => 'created_by', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'show_updated_at', 'name' => 'updated_at', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'updated_by_name', 'name' => 'updated_by_name', 'searchable' => '1', 'orderable' => '1'],
            ['data' => 'Status', 'name' => 'Status', 'searchable' => '', 'orderable' => '1'],
            ['data' => 'id', 'name' => 'Action', 'searchable' => '', 'orderable' => ''],
        ];

        $query = [];
        foreach ($cols as $i => $col) {
            $query["columns[$i][data]"] = $col['data'];
            $query["columns[$i][name]"] = $col['name'];
            $query["columns[$i][searchable]"] = $col['searchable'];
            $query["columns[$i][orderable]"] = $col['orderable'];
            $query["columns[$i][search][value]"] = '';
            $query["columns[$i][search][regex]"] = 'false';
        }

        return $query;
    }
}
