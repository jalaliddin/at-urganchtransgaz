<?php

namespace App\Models;

use App\Enums\CartridgeColor;
use App\Enums\CartridgeKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cartridge / toner model (global catalog). Its stock, receipts and issues are
 * those of its warehouse material (design P3).
 *
 * @property int $id
 * @property int $material_id
 * @property string $name
 * @property string|null $part_number
 * @property CartridgeKind $kind
 * @property CartridgeColor $color
 * @property int|null $page_yield
 * @property bool $is_active
 * @property-read Material $material
 */
class CartridgeModel extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['material_id', 'name', 'part_number', 'kind', 'color', 'page_yield', 'is_active'];

    protected function casts(): array
    {
        return [
            'kind' => CartridgeKind::class,
            'color' => CartridgeColor::class,
            'page_yield' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Printer models (asset models of the printer category) this cartridge fits.
     *
     * @return BelongsToMany<AssetModel, $this>
     */
    public function printerModels(): BelongsToMany
    {
        return $this->belongsToMany(AssetModel::class, 'printer_cartridge_compatibilities', 'cartridge_model_id', 'asset_model_id');
    }
}
