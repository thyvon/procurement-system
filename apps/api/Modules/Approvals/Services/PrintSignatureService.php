<?php

namespace Modules\Approvals\Services;

use App\Support\DataUri;
use App\Support\PrintFormatter;
use Illuminate\Support\Facades\Storage;
use Modules\Approvals\Models\ApprovalAction;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Repositories\ApprovalRequestRepositoryInterface;

/**
 * The official-sheet signature block, owned entirely by the approvals
 * module: one column per print-visible approval step of the document's
 * newest round — a co-signed step expands to one column per responsible
 * user, each carrying that signer's own decision. Names and positions come
 * from the frozen submit snapshot, the date from the step's action (or the
 * submission for record steps), and a signature only once the step carries a
 * decision — a `cancel` is a withdrawal, not a decision, and pending steps
 * show an empty line.
 *
 * Titles stack the flow's bilingual step names (`label` over `labelKh`).
 * There is no hardcoded fallback: a document without an approval round
 * prints an empty signature block until an admin configures a flow.
 */
class PrintSignatureService
{
    public function __construct(private readonly ApprovalRequestRepositoryInterface $requests) {}

    /**
     * @return list<array{key: string, title: string, titleKh: ?string, name: string, position: string, date: string, signature: ?string}>
     */
    public function forSubject(string $subjectType, string $subjectId): array
    {
        return $this->columns($this->requests->latestForSubject($subjectType, $subjectId));
    }

    /**
     * @return list<array{key: string, title: string, titleKh: ?string, name: string, position: string, date: string, signature: ?string}>
     */
    private function columns(?ApprovalRequest $round): array
    {
        if ($round === null) {
            return [];
        }

        $steps = collect($round->snapshot['steps'] ?? [])
            ->filter(fn (array $step): bool => ($step['showOnPrint'] ?? true) !== false)
            ->values();

        if ($steps->isEmpty()) {
            return [];
        }

        $submittedAt = $round->created_at?->format('Y-m-d H:i');

        return $steps->flatMap(function (array $step) use ($round, $submittedAt): array {
            $signers = array_values(array_filter(
                $step['assignees'] ?? [],
                fn (array $signer): bool => ($signer['name'] ?? '') !== ''
            ));

            // Snapshots predating co-signers (and record steps) freeze one
            // assignee — a single column, exactly as before.
            if ($signers === []) {
                $action = $this->lastAction($round, $step);
                $decided = $action !== null && $action->action !== 'cancel';

                return [[
                    'key' => (string) ($step['key'] ?? ''),
                    'title' => (string) ($step['label'] ?? ''),
                    'titleKh' => $this->titleKh($step),
                    'name' => (string) ($step['assigneeName'] ?? $action?->actor?->name ?? ''),
                    'position' => (string) ($step['assigneePosition'] ?? ''),
                    'date' => $this->date($step, $decided ? $action : null, $submittedAt),
                    'signature' => $decided ? $this->signature($step['assigneeSignature'] ?? null) : null,
                ]];
            }

            // A co-signed step prints one column per responsible user; each
            // column carries that signer's own decision.
            return array_map(function (array $signer) use ($step, $round, $submittedAt): array {
                $action = $this->signerAction($round, $step, (int) ($signer['id'] ?? 0));
                $decided = $action !== null && $action->action !== 'cancel';

                return [
                    'key' => (string) ($step['key'] ?? ''),
                    'title' => (string) ($step['label'] ?? ''),
                    'titleKh' => $this->titleKh($step),
                    'name' => (string) ($signer['name'] ?? ''),
                    'position' => (string) ($signer['position'] ?? ''),
                    'date' => $this->date($step, $decided ? $action : null, $submittedAt),
                    'signature' => $decided ? $this->signature($signer['signature'] ?? null) : null,
                ];
            }, $signers);
        })->all();
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function titleKh(array $step): ?string
    {
        return isset($step['labelKh']) && $step['labelKh'] !== null && $step['labelKh'] !== ''
            ? (string) $step['labelKh']
            : null;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function date(array $step, ?ApprovalAction $action, ?string $submittedAt): string
    {
        if (($step['actionMode'] ?? null) === 'record') {
            return PrintFormatter::dateOnly($submittedAt);
        }

        return PrintFormatter::dateOnly($action?->acted_at?->format('Y-m-d H:i'));
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function lastAction(ApprovalRequest $round, array $step): ?ApprovalAction
    {
        return $round->actions
            ->where('step_position', $step['position'] ?? null)
            ->last();
    }

    /**
     * A co-signer's column shows their own act on the step — an unacted
     * co-signer (the round closed early) keeps an empty decision line.
     *
     * @param  array<string, mixed>  $step
     */
    private function signerAction(ApprovalRequest $round, array $step, int $signerId): ?ApprovalAction
    {
        if ($signerId <= 0) {
            return null;
        }

        return $round->actions
            ->where('step_position', $step['position'] ?? null)
            ->where('acted_by', $signerId)
            ->last();
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
