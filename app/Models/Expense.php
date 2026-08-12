<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Money the company spent running itself.
 *
 * Deliberately not a vendor bill: a bill is owed to somebody and settled later,
 * while an expense is simply money gone. Keeping them apart is what lets the
 * report show what is still payable separately from what has been spent.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $bank_id
 * @property int $created_by
 * @property ExpenseCategory $category
 * @property string $description
 * @property Carbon $spent_on
 * @property int $amount
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Bank|null $bank
 * @property-read User $creator
 */
#[Fillable([
    'team_id',
    'bank_id',
    'created_by',
    'category',
    'description',
    'spent_on',
    'amount',
    'note',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'category' => ExpenseCategory::class,
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
