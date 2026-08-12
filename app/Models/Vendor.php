<?php

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Someone the company buys from.
 *
 * The mirror of a Distributor: bills are what they charge us, payments are what
 * we send them, and `balance` is what we still owe — the same running account,
 * pointing the other way.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $proprietor_name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $thana
 * @property string|null $district
 * @property string|null $division
 * @property int $balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Collection<int, VendorBill> $bills
 * @property-read Collection<int, VendorPayment> $payments
 */
#[Fillable([
    'team_id',
    'name',
    'proprietor_name',
    'phone',
    'address',
    'thana',
    'district',
    'division',
    'balance',
])]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<VendorBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    /**
     * @return HasMany<VendorPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    /**
     * The single place the address line is assembled, matching Distributor.
     */
    public function fullAddress(): string
    {
        return collect([$this->address, $this->thana, $this->district, $this->division])
            ->filter(fn (?string $part) => filled($part))
            ->implode(', ');
    }
}
