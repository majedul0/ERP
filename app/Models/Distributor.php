<?php

namespace App\Models;

use Database\Factories\DistributorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
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
 * @property-read Collection<int, Invoice> $invoices
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
class Distributor extends Model
{
    /** @use HasFactory<DistributorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The single place the address line is assembled, so the invoice screen
     * and the distributor list cannot drift apart.
     */
    public function fullAddress(): string
    {
        return collect([$this->address, $this->thana, $this->district, $this->division])
            ->filter(fn (?string $part) => filled($part))
            ->implode(', ');
    }
}
