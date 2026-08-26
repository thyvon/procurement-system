<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Storage;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class StorageProbeCheck extends Check
{
    public function run(): Result
    {
        try {
            $disk = Storage::disk();
            $path = 'health/'.bin2hex(random_bytes(8)).'.probe';
            $disk->put($path, 'ok');
            $disk->delete($path);

            return Result::make()->ok();
        } catch (\Throwable $e) {
            return Result::make()->failed("Storage probe failed: {$e->getMessage()}");
        }
    }
}
