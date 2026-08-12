<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * What a vendor has charged the company.
 *
 * @property int $id
 * @property int $team_id
 * @property int $vendor_id
 * @property int $created_by
 * @property string|null $reference
 * @property string|null $description
 * @property Carbon $billed_on
 * @property int $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Vendor $vendor
 * @property-read User $creator
 */
#[Fillable([
    'team_id',
    'vendor_id',
    'created_by',
    'reference',
    'description',
    'billed_on',
    'amount',
])]
class VendorBill extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billed_on' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
