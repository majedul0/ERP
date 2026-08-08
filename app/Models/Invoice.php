<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $distributor_id
 * @property int|null $created_by
 * @property string $invoice_number
 * @property int $sequence_number
 * @property Carbon $sold_at
 * @property DeliveryStatus $delivery_status
 * @property string|null $comment
 * @property string|null $scheme_description
 * @property int $scheme_amount
 * @property int $invoice_total
 * @property int $discount_total
 * @property int $previous_dues
 * @property int $total_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Distributor $distributor
 * @property-read User|null $creator
 * @property-read Collection<int, InvoiceItem> $items
 */
#[Fillable([
    'team_id',
    'distributor_id',
    'created_by',
    'invoice_number',
    'sequence_number',
    'sold_at',
    'delivery_status',
    'comment',
    'scheme_description',
    'scheme_amount',
    'invoice_total',
    'discount_total',
    'previous_dues',
    'total_amount',
])]
class Invoice extends Model
{
    use SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_number');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'delivery_status' => DeliveryStatus::class,
        ];
    }
}
