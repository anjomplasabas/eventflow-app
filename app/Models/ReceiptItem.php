<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $receipt_id
 * @property string $label
 * @property string|null $description
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property int $display_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Receipt $receipt
 */
#[Fillable([
    'receipt_id', 'label', 'description', 'quantity',
    'unit_price_minor', 'line_total_minor', 'display_order',
])]
class ReceiptItem extends Model
{
    /** @use HasFactory<ReceiptItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
