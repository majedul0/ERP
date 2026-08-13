<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What the platform sells: a name, a price and how long it lasts.
 *
 * Deliberately carries no limits on users, invoices or storage — tiers here
 * price the same product, and enforcing caps is a separate decision that
 * nothing yet depends on.
 *
 * @property int $id
 * @property string $name
 * @property int $price
 * @property BillingPeriod $period
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Team> $teams
 */
#[Fillable(['name', 'price', 'period', 'is_active'])]
class Plan extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => BillingPeriod::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * What this plan is worth per month, for comparing tiers side by side.
     */
    public function monthlyValue(): int
    {
        return $this->period->monthlyEquivalent($this->price);
    }
}
