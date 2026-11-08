<?php

namespace App\Services\Requisitions;

use App\Enums\RequisitionAction;
use App\Enums\RequisitionStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Material;
use App\Models\Requisition;
use App\Models\RequisitionAttachment;
use App\Models\RequisitionComment;
use App\Models\RequisitionItem;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\FileStore;
use App\Services\Support\NumberSequence;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Drafts, items, comments and files of requisitions. Status changes live in RequisitionWorkflow. */
class RequisitionService
{
    /** @var list<string> */
    private const FIELDS = ['title', 'description', 'needed_by', 'recipient_employee_id'];

    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly RequisitionWorkflow $workflow,
        private readonly FileStore $files,
    ) {}

    /**
     * @param  array<string, mixed>  $data  organization_id + FIELDS
     * @param  list<array<string, mixed>>  $items
     */
    public function create(array $data, array $items, User $by): Requisition
    {
        return DB::transaction(function () use ($data, $items, $by): Requisition {
            $year = CarbonImmutable::now((string) config('app.display_timezone'))->year;
            $requisition = Requisition::query()->withoutGlobalScopes()->create([
                ...array_intersect_key($data, array_flip(self::FIELDS)),
                'organization_id' => (int) $data['organization_id'],
                'number' => sprintf('RQ-%d-%05d', $year, $this->numbers->next("requisition:{$year}")),
                'status' => RequisitionStatus::Draft,
                'requester_id' => $by->id,
            ]);
            $this->replaceItems($requisition, $items);
            $this->workflow->record($requisition, RequisitionAction::Created, $by, null, RequisitionStatus::Draft);

            return $requisition;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $items  null keeps the items
     */
    public function update(Requisition $requisition, array $data, ?array $items, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $data, $items, $by): void {
            $this->workflow->lockFor('edit', $requisition, $by);
            $requisition->fill(array_intersect_key($data, array_flip(self::FIELDS)))->save();
            if ($items !== null) {
                $this->replaceItems($requisition, $items);
            }
            $this->workflow->record($requisition, RequisitionAction::Updated, $by);
        });

        return $requisition;
    }

    public function delete(Requisition $requisition, User $by): void
    {
        DB::transaction(function () use ($requisition, $by): void {
            $this->workflow->lockFor('delete', $requisition, $by);
            // Once submitted, its approvals and history must stay (spec: every step is kept).
            if ($requisition->submitted_at !== null) {
                throw new InvalidTransitionException(__('messages.requisition_delete_submitted'));
            }
            $files = RequisitionAttachment::query()->where('requisition_id', $requisition->id)->with('file')->get();
            $requisition->delete();
            $files->each(fn (RequisitionAttachment $a) => $this->files->delete($a->file));
        });
    }

    public function comment(Requisition $requisition, string $body, User $by): RequisitionComment
    {
        return DB::transaction(function () use ($requisition, $body, $by): RequisitionComment {
            $this->workflow->lockFor('comment', $requisition, $by);

            return RequisitionComment::create(['requisition_id' => $requisition->id, 'user_id' => $by->id, 'body' => $body]);
        });
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @return list<RequisitionAttachment>
     */
    public function attach(Requisition $requisition, array $uploads, User $by): array
    {
        $stored = array_map(fn (UploadedFile $u) => $this->files->store($u, $requisition->organization_id, $by), $uploads);

        return DB::transaction(function () use ($requisition, $stored, $by): array {
            $this->workflow->lockFor('comment', $requisition, $by);
            $created = array_map(fn ($file) => RequisitionAttachment::create([
                'requisition_id' => $requisition->id, 'file_id' => $file->id, 'uploaded_by' => $by->id,
            ]), $stored);
            $this->workflow->record($requisition, RequisitionAction::FileAdded, $by, payload: [
                'files' => array_map(fn ($file) => $file->original_name, $stored),
            ]);

            return $created;
        });
    }

    /** FileAccess resolver: attachments follow the requisition's visibility. */
    public function canDownload(User $user, StoredFile $file): ?bool
    {
        $id = RequisitionAttachment::query()->where('file_id', $file->id)->value('requisition_id');
        if ($id === null) {
            return null;
        }

        return Requisition::query()->visibleTo($user)->whereKey($id)->exists();
    }

    /** @param  list<array<string, mixed>>  $items */
    private function replaceItems(Requisition $requisition, array $items): void
    {
        RequisitionItem::query()->where('requisition_id', $requisition->id)->delete();
        $materials = Material::query()->with('unit')->whereIn('id', array_filter(array_map(fn ($i) => $i['material_id'] ?? null, $items)))->get()->keyBy('id');
        foreach ($items as $index => $item) {
            $material = isset($item['material_id']) ? $materials->get((int) $item['material_id']) : null;
            RequisitionItem::create([
                'requisition_id' => $requisition->id,
                'kind' => $item['kind'],
                'material_id' => $material?->id,
                'asset_category_id' => $item['asset_category_id'] ?? null,
                'name' => trim((string) ($item['name'] ?? '')) !== '' ? $item['name'] : ($material->name ?? ''),
                'quantity' => Decimal::of($item['quantity']),
                'unit_id' => $item['unit_id'] ?? $material?->unit_id,
                'note' => $item['note'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }
}
