<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Money the company has sent a vendor.
 *
 * @property int $id
 * @property int $team_id
 * @property int $vendor_id
 * @property int|null $bank_id
 * @property int $created_by
 * @property Carbon $paid_on
 * @property int $amount
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Vendor $vendor
 * @property-read Bank|null $bank
 * @property-read User $creator
 */
#[Fillable([
    'team_id',
    'vendor_id',
    'bank_id',
    'created_by',
    'paid_on',
    'amount',
    'comment',
])]
class VendorPayment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
