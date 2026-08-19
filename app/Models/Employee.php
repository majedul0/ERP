<?php

namespace App\Models;

use App\Enums\SalaryType;
use App\Support\TenantFileStore;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Somebody who works for the company.
 *
 * Not a `User`. A login needs a unique email address and a password; a packer
 * has neither and never signs in. Keeping the two apart also keeps salary
 * figures off the account of every invited accountant and platform admin.
 *
 * `balance` is what the company still owes this person — derived from the
 * approved payroll lines and the salary payments by ReplayEmployeeBalance, and
 * never typed. Negative means they have drawn more than they have earned, which
 * is an outstanding advance; the same convention a vendor's balance uses.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $department_id
 * @property int|null $created_by
 * @property string $employee_code
 * @property string $name
 * @property string|null $father_name
 * @property string|null $phone
 * @property string|null $nid
 * @property string|null $designation
 * @property string|null $photo_path
 * @property string|null $address
 * @property string|null $thana
 * @property string|null $district
 * @property string|null $division
 * @property SalaryType $salary_type
 * @property Carbon $joined_on
 * @property Carbon|null $left_on
 * @property int $balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Department|null $department
 * @property-read User|null $creator
 */
#[Fillable([
    'team_id',
    'department_id',
    'created_by',
    'employee_code',
    'name',
    'father_name',
    'phone',
    'nid',
    'designation',
    'photo_path',
    'address',
    'thana',
    'district',
    'division',
    'salary_type',
    'joined_on',
    'left_on',
    'balance',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether this person was employed here on a given day.
     *
     * Both ends inclusive: somebody who joined on the 1st worked that day, and
     * somebody who left on the 20th was owed for it. Payroll asks this of every
     * day it counts, so a mid-month joiner is paid from the day they started
     * rather than for the whole month.
     *
     * CarbonInterface, not Carbon: the app runs on CarbonImmutable — see
     * AppServiceProvider and the same note on App\Data\LedgerEntry.
     */
    public function wasEmployedOn(CarbonInterface $date): bool
    {
        if ($date->lt($this->joined_on->startOfDay())) {
            return false;
        }

        return $this->left_on === null || $date->lte($this->left_on->endOfDay());
    }

    /**
     * Still on the payroll today.
     */
    public function isActive(): bool
    {
        return $this->left_on === null;
    }

    /**
     * The public URL of the employee photo, or null when none is uploaded.
     */
    public function photoUrl(): ?string
    {
        return TenantFileStore::url('employee_photos', $this->photo_path);
    }

    /**
     * The single place the address line is assembled, matching Vendor.
     */
    public function fullAddress(): string
    {
        return collect([$this->address, $this->thana, $this->district, $this->division])
            ->filter(fn (?string $part) => filled($part))
            ->implode(', ');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
            'salary_type' => SalaryType::class,
        ];
    }
}
