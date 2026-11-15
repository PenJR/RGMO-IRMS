<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\IncidentReport;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class IncidentReportService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function submit(array $data, array $evidence, User $reporter): IncidentReport
    {
        $paths = [];
        try {
            foreach ($evidence as $file) {
                if ($file instanceof UploadedFile) {
                    $paths[] = $file->store('incident-evidence', 'local');
                }
            }

            $incident = DB::transaction(function () use ($data, $paths, $reporter) {
                $incident = IncidentReport::create($data + [
                    'reported_by' => $reporter->id,
                    'evidence_paths' => $paths,
                ]);
                AuditLog::log($reporter->id, 'create', 'incident_report', IncidentReport::class, $incident->id, null, $incident->toArray());

                return $incident;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        $this->notifyRoles([User::ROLE_STOREKEEPER], $reporter, $incident, 'New incident report requires investigation.');

        return $incident;
    }

    public function investigate(IncidentReport $incident, string $notes, User $investigator): IncidentReport
    {
        return DB::transaction(function () use ($incident, $notes, $investigator) {
            $locked = IncidentReport::query()->lockForUpdate()->findOrFail($incident->id);
            Gate::forUser($investigator)->authorize('investigate', $locked);
            $before = $locked->toArray();
            $locked->update([
                'status' => IncidentReport::STATUS_INVESTIGATING,
                'investigated_by' => $investigator->id,
                'investigated_at' => now(),
                'investigation_notes' => $notes,
            ]);
            AuditLog::log($investigator->id, 'investigate', 'incident_report', IncidentReport::class, $locked->id, $before, $locked->fresh()->toArray());
            $this->notifyRoles([User::ROLE_ADMIN], $investigator, $locked, 'An incident investigation is ready for resolution.');

            return $locked->fresh();
        });
    }

    public function resolve(IncidentReport $incident, array $data, User $resolver, StockAdjustmentService $adjustments): IncidentReport
    {
        return DB::transaction(function () use ($incident, $data, $resolver, $adjustments) {
            $locked = IncidentReport::query()->lockForUpdate()->findOrFail($incident->id);
            Gate::forUser($resolver)->authorize('resolve', $locked);
            if ($data['resolution'] === 'stock_adjustment') {
                $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);
                $transaction = $adjustments->adjust($item, [
                    'adjustment_type' => $data['adjustment_type'],
                    'quantity' => $data['quantity'],
                    'reason' => $data['adjustment_reason'],
                    'incident_report_reference' => $locked->reference(),
                    'idempotency_key' => 'incident-report-'.$locked->id,
                    'inventory_batch_id' => $data['inventory_batch_id'] ?? null,
                    'batch_number' => $data['batch_number'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                ], $resolver);
                $locked->inventory_transaction_id = $transaction->id;
            } elseif ($data['resolution'] === 'rejected' && blank($data['resolution_notes'])) {
                throw ValidationException::withMessages(['resolution_notes' => 'Provide a reason when rejecting or closing this incident.']);
            }

            $before = $locked->toArray();
            $locked->update([
                'status' => $data['resolution'] === 'rejected' ? IncidentReport::STATUS_CLOSED : IncidentReport::STATUS_RESOLVED,
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
                'resolution' => $data['resolution'],
                'resolution_notes' => $data['resolution_notes'],
            ]);
            AuditLog::log($resolver->id, 'resolve', 'incident_report', IncidentReport::class, $locked->id, $before, $locked->fresh()->toArray());
            if ($locked->reported_by) {
                $this->notifications->createNotification($locked->reported_by, 'incident_resolved',
                    'Incident '.$locked->reference().' has been resolved.', [
                        'title' => 'Incident report resolved',
                        'sender_id' => $resolver->id,
                        'data' => ['incident_id' => $locked->id, 'resolution' => $locked->resolution],
                    ]);
            }

            return $locked->fresh();
        }, 5);
    }

    private function notifyRoles(array $roles, User $sender, IncidentReport $incident, string $message): void
    {
        $users = User::query()->whereIn('role', $roles)->where('status', User::STATUS_ACTIVE)->pluck('id')->all();
        $this->notifications->createBulkNotification($users, 'incident_report', $message.' '.$incident->reference(), [
            'title' => 'Incident report requires attention',
            'sender_id' => $sender->id,
            'data' => ['incident_id' => $incident->id, 'status' => $incident->status],
        ]);
    }
}
