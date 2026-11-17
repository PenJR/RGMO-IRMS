<?php

namespace App\Services;

use App\Exceptions\ProcurementDraftConflictException;
use App\Models\Procurement;
use App\Models\ProcurementDraft;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class ProcurementDraftService
{
    public function save(User $user, string $id, int $expectedRevision, array $payload, ?UploadedFile $receipt = null, bool $removeReceipt = false): ProcurementDraft
    {
        Gate::forUser($user)->authorize('create', ProcurementDraft::class);
        $id = $this->normalizeId($id);
        $existing = ProcurementDraft::find($id);
        if ($existing) {
            Gate::forUser($user)->authorize('view', $existing);
            $this->ensureCurrent($existing, $expectedRevision);
        } else {
            abort_unless($expectedRevision === 0, 404, 'The unfinished purchase was not found.');
        }

        $newPath = null;
        try {
            if ($receipt) {
                $newPath = $receipt->store('procurement-draft-receipts/'.$id, 'local');
                if (! $newPath) {
                    throw new RuntimeException('The receipt could not be saved. Your previous saved draft has not changed.');
                }
            }

            return DB::transaction(function () use ($user, $id, $expectedRevision, $payload, $receipt, $removeReceipt, $newPath) {
                $draft = ProcurementDraft::query()->lockForUpdate()->find($id);
                if ($draft) {
                    Gate::forUser($user)->authorize('view', $draft);
                    $this->ensureCurrent($draft, $expectedRevision);
                } else {
                    abort_unless($expectedRevision === 0, 404, 'The unfinished purchase was not found.');
                    $draft = new ProcurementDraft(['id' => $id, 'user_id' => $user->id, 'revision' => 0]);
                }

                $oldPath = $draft->receipt_path;
                $draft->fill(['payload' => $payload, 'revision' => $draft->revision + 1]);
                if ($newPath) {
                    $draft->receipt_path = $newPath;
                    $draft->receipt_original_name = $this->receiptName($receipt);
                } elseif ($removeReceipt) {
                    $draft->receipt_path = null;
                    $draft->receipt_original_name = null;
                }
                $draft->save();
                if ($oldPath && $oldPath !== $draft->receipt_path) {
                    DB::afterCommit(fn () => $this->deleteObsoleteReceipt($oldPath));
                }

                return $draft;
            }, 5);
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                $current = ProcurementDraft::find($id);
                if ($current) {
                    Gate::forUser($user)->authorize('view', $current);
                    throw new ProcurementDraftConflictException($current, 'This unfinished purchase was saved by another request. Reload the saved version before continuing.');
                }
            }
            throw $exception;
        }
    }

    public function discard(User $user, string $id, int $expectedRevision): ProcurementDraft
    {
        Gate::forUser($user)->authorize('create', ProcurementDraft::class);
        $id = $this->normalizeId($id);

        try {
            return DB::transaction(function () use ($user, $id, $expectedRevision) {
                $draft = ProcurementDraft::query()->lockForUpdate()->find($id);
                if ($draft) {
                    Gate::forUser($user)->authorize('view', $draft);
                    $this->ensureCurrent($draft, $expectedRevision);
                } else {
                    // Discard can arrive before an in-flight first autosave.
                    // Keep its identifier so that request cannot recreate it.
                    abort_unless($expectedRevision === 0, 404, 'The unfinished purchase was not found.');
                    $draft = new ProcurementDraft(['id' => $id, 'user_id' => $user->id, 'revision' => 0]);
                }

                $oldPath = $draft->receipt_path;
                $draft->fill([
                    'status' => ProcurementDraft::STATUS_DISCARDED,
                    'revision' => $draft->revision + 1,
                    'payload' => [],
                    'receipt_path' => null,
                    'receipt_original_name' => null,
                ])->save();
                if ($oldPath) {
                    DB::afterCommit(fn () => $this->deleteObsoleteReceipt($oldPath));
                }

                return $draft;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $current = ProcurementDraft::find($id);
            if ($current) {
                Gate::forUser($user)->authorize('view', $current);
                throw new ProcurementDraftConflictException($current, 'This unfinished purchase changed before it could be discarded. Reload the saved version before continuing.');
            }
            throw $exception;
        }
    }

    public function lockForConversion(User $user, string $id, int $expectedRevision): ProcurementDraft
    {
        $this->requireTransaction();
        $draft = ProcurementDraft::query()->lockForUpdate()->findOrFail($this->normalizeId($id));
        Gate::forUser($user)->authorize('view', $draft);
        $this->ensureCurrent($draft, $expectedRevision);

        return $draft;
    }

    public function complete(ProcurementDraft $draft, Procurement $procurement): ProcurementDraft
    {
        $this->requireTransaction();
        $locked = ProcurementDraft::query()->lockForUpdate()->findOrFail($draft->id);
        $this->ensureCurrent($locked, $draft->revision);
        if ((int) $procurement->created_by !== $locked->user_id) {
            throw ValidationException::withMessages(['autosave_draft' => 'The saved purchase must belong to the draft owner.']);
        }
        $oldPath = $locked->receipt_path;
        $locked->update([
            'status' => ProcurementDraft::STATUS_COMPLETED,
            'revision' => $locked->revision + 1,
            'completed_procurement_id' => $procurement->id,
            'payload' => [],
            'receipt_path' => null,
            'receipt_original_name' => null,
        ]);
        if ($oldPath && $oldPath !== $procurement->receipt_path) {
            DB::afterCommit(fn () => $this->deleteObsoleteReceipt($oldPath));
        }

        return $locked;
    }

    private function ensureCurrent(ProcurementDraft $draft, int $expectedRevision): void
    {
        if (! $draft->isActive()) {
            throw new ProcurementDraftConflictException($draft, $draft->status === ProcurementDraft::STATUS_COMPLETED
                ? 'This unfinished purchase has already been saved as a purchase order.'
                : 'This unfinished purchase has already been discarded. Start a new purchase instead.');
        }
        if ($draft->revision !== $expectedRevision) {
            throw new ProcurementDraftConflictException($draft, 'This unfinished purchase was changed in another tab or request. Reload the saved version before continuing.');
        }
    }

    private function normalizeId(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw ValidationException::withMessages(['draft_id' => 'Use a valid unfinished purchase identifier.']);
        }

        return mb_strtolower($id);
    }

    private function deleteObsoleteReceipt(string $path): void
    {
        try {
            Storage::disk('local')->delete($path);
        } catch (\Throwable $exception) {
            // A cleanup failure must not invalidate an already committed save
            // or cause its newly referenced receipt to be deleted by the caller.
            report($exception);
        }
    }

    private function receiptName(UploadedFile $receipt): string
    {
        $name = basename(str_replace('\\', '/', $receipt->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        return mb_substr($name ?: 'receipt', 0, 255);
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Draft conversion must share the purchase order transaction.');
        }
    }
}
