<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Spatie\Health\Facades\Health;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = collect(Health::registeredChecks())
            ->map(function ($check) {
                try {
                    $result = $check->run();
                    $status = $result->status->value ?? 'ok';
                    $message = $result->getNotificationMessage();
                } catch (\Throwable $e) {
                    $status = 'crashed';
                    $message = $e->getMessage();
                }

                return [
                    'name' => $check->getName(),
                    'status' => $status,
                    'message' => $message,
                ];
            })
            ->values();

        $healthy = $checks->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'healthy' => $healthy,
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
