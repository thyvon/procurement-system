<?php

namespace Modules\Approvals\Services;

use App\Support\DataUri;
use App\Support\PrintFormatter;
use Illuminate\Support\Facades\Storage;
use Modules\Approvals\Models\ApprovalRequest;

/**
 * The official-sheet signature block: one column per print-visible approval
 * step of the document's newest round, mirroring `signatureColumns()` in the
 * web print sheets (evaluation, cash request, cash clearance, advance
 * liquidation). Names and positions come from the frozen submit snapshot,
 * the date from the step's action (or the submission for record steps), and
 * a signature only once the step carries a decision — a `cancel` is a
 * withdrawal, not a decision, and pending steps show an empty line.
 */
class PrintSignatureService
{
    /**
     * @param  array<int, array{key: string, title: string}>  $fallback  columns shown when there is no round (or no print-visible step)
     * @return list<array{key: string, title: string, name: string, position: string, date: string, signature: ?string}>
     */
    public function columns(?ApprovalRequest $round, array $fallback): array
    {
        if ($round === null) {
            return $this->empty($fallback);
        }

        $steps = collect($round->snapshot['steps'] ?? [])
            ->filter(fn (array $step): bool => ($step['showOnPrint'] ?? true) !== false)
            ->values();

        if ($steps->isEmpty()) {
            return $this->empty($fallback);
        }

        $submittedAt = $round->created_at?->format('Y-m-d H:i');

        return $steps->map(function (array $step) use ($round, $submittedAt): array {
            $action = $round->actions
                ->where('step_position', $step['position'] ?? null)
                ->last();
            $decided = $action !== null && $action->action !== 'cancel';
            $name = $step['assigneeName'] ?? $action?->actor?->name ?? '';

            $date = ($step['actionMode'] ?? null) === 'record'
                ? PrintFormatter::dateOnly($submittedAt)
                : PrintFormatter::dateOnly($action?->acted_at?->format('Y-m-d H:i'));

            return [
                'key' => (string) ($step['key'] ?? ''),
                'title' => (string) ($step['label'] ?? ''),
                'name' => (string) $name,
                'position' => (string) ($step['assigneePosition'] ?? ''),
                'date' => $date,
                'signature' => $decided ? $this->signature($step['assigneeSignature'] ?? null) : null,
            ];
        })->all();
    }

    /**
     * @param  array<int, array{key: string, title: string}>  $fallback
     * @return list<array{key: string, title: string, name: string, position: string, date: string, signature: ?string}>
     */
    private function empty(array $fallback): array
    {
        return array_map(
            fn (array $column): array => [
                'key' => $column['key'],
                'title' => $column['title'],
                'name' => '',
                'position' => '',
                'date' => '',
                'signature' => null,
            ],
            $fallback,
        );
    }

    /**
     * Snapshots freeze the signature path at submit time; inline it as a
     * data-URI (PDF rendering must not fetch over the network) and fall back
     * to the public URL when the file cannot be read locally.
     */
    private function signature(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, '://')) {
            return $path;
        }

        return DataUri::fromPublic($path) ?? Storage::disk('public')->url($path);
    }
}
