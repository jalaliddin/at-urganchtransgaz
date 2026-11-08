<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money paid against an approved invoice.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $amount
 * @property CarbonImmutable $paid_at
 * @property string|null $document_number
 * @property int|null $file_id
 * @property string|null $note
 * @property int|null $created_by
 * @property-read User|null $creator
 * @property-read StoredFile|null $file
 */
class ServicePayment extends Model
{
    use Auditable;

    protected $fillable = ['invoice_id', 'amount', 'paid_at', 'document_number', 'file_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'immutable_date'];
    }

    /** @return BelongsTo<ServiceInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ServiceInvoice::class, 'invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<StoredFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }
}
