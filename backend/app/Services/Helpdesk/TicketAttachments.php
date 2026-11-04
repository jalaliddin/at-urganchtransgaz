<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Models\StoredFile;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\Files\FileStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Ticket attachments on the private disk. Download rights follow the ticket
 * (and stay internal when attached to an internal comment) — see canDownload().
 */
class TicketAttachments
{
    /** Uploaders may remove their own file within this many minutes. */
    public const OWN_DELETE_MINUTES = 15;

    public function __construct(
        private readonly FileStore $files,
        private readonly TicketService $tickets,
        private readonly TicketWorkflow $workflow,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @return Collection<int, TicketAttachment>
     */
    public function attach(Ticket $ticket, array $uploads, User $by, ?int $commentId = null, bool $internal = false): Collection
    {
        $files = $this->store($uploads, $ticket->organization_id, $by);
        try {
            return $this->link($ticket, $files, $by, $commentId, $internal);
        } catch (\Throwable $e) {
            $this->discard($files);
            throw $e;
        }
    }

    /**
     * Writes uploads to the private disk (no ticket yet), so callers can do the slow
     * I/O before taking locks.
     *
     * @param  list<UploadedFile>  $uploads
     * @return list<StoredFile>
     */
    public function store(array $uploads, ?int $organizationId, User $by): array
    {
        $files = [];
        try {
            foreach ($uploads as $upload) {
                $files[] = $this->files->store($upload, $organizationId, $by);
            }
        } catch (\Throwable $e) {
            $this->discard($files);
            throw $e;
        }

        return $files;
    }

    /**
     * @param  list<StoredFile>  $files
     * @return Collection<int, TicketAttachment>
     */
    public function link(Ticket $ticket, array $files, User $by, ?int $commentId = null, bool $internal = false): Collection
    {
        /** @var Collection<int, TicketAttachment> $created */
        $created = new Collection;
        foreach ($files as $file) {
            $created->push(TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'comment_id' => $commentId,
                'stored_file_id' => $file->id,
                'user_id' => $by->id,
            ]));
        }
        if ($files !== []) {
            $this->tickets->history($ticket, $by, TicketHistoryAction::AttachmentAdded, new: [
                'files' => array_map(fn (StoredFile $f) => $f->original_name, $files),
            ], internal: $internal);
        }

        return $created;
    }

    /**
     * Removes stored files whose owner was never saved (failed or rolled-back request).
     *
     * @param  list<StoredFile>  $files
     */
    public function discard(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk($file->disk)->delete($file->path);
            StoredFile::query()->whereKey($file->id)->delete();
        }
    }

    public function canDelete(TicketAttachment $attachment, Ticket $ticket, User $user): bool
    {
        if ($this->workflow->isHandler($ticket, $user)) {
            return true;
        }

        return $attachment->user_id === $user->id
            && $attachment->created_at !== null
            && $attachment->created_at->addMinutes(self::OWN_DELETE_MINUTES)->isFuture();
    }

    public function delete(TicketAttachment $attachment, Ticket $ticket, User $by): void
    {
        $name = $attachment->file->original_name;
        $internal = (bool) $attachment->comment?->is_internal;
        $this->files->delete($attachment->file);
        $attachment->delete();
        $this->tickets->history($ticket, $by, TicketHistoryAction::AttachmentRemoved, old: ['files' => [$name]], internal: $internal);
    }

    /** FileAccess resolver: null for files that are not ticket attachments. */
    public function canDownload(User $user, StoredFile $file): ?bool
    {
        $attachment = TicketAttachment::query()->with('comment')->where('stored_file_id', $file->id)->first();
        if ($attachment === null) {
            return null;
        }
        $ticket = Ticket::query()->visibleTo($user)->find($attachment->ticket_id);
        if ($ticket === null) {
            return false;
        }

        return ! $attachment->comment?->is_internal || $this->workflow->isHandler($ticket, $user);
    }
}
