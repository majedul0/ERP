<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\SuspensionMode;
use App\Enums\TeamRole;
use App\Support\BrandColor;
use App\Support\TenantFileStore;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string|null $theme_color
 * @property string|null $address
 * @property string|null $phone
 * @property bool $is_personal
 * @property Carbon|null $suspended_at
 * @property SuspensionMode $suspension_mode
 * @property int|null $plan_id
 * @property Carbon|null $paid_through
 * @property-read Plan|null $plan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Distributor> $distributors
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, SalesReturn> $salesReturns
 * @property-read Collection<int, Bank> $banks
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Department> $departments
 * @property-read Collection<int, Employee> $employees
 * @property-read Collection<int, Holiday> $holidays
 * @property-read Collection<int, AttendanceRecord> $attendanceRecords
 * @property-read Collection<int, PayrollRun> $payrollRuns
 * @property-read Collection<int, SalaryPayment> $salaryPayments
 * @property-read Collection<int, EmployeeBonus> $employeeBonuses
 * @property-read Collection<int, CompanyDocument> $documents
 */
#[Fillable([
    'name',
    'slug',
    'logo_path',
    'theme_color',
    'address',
    'phone',
    'is_personal',
    'suspended_at',
    'suspension_mode',
    'plan_id',
    // Written only by ReplaySubscription — see the migration that added it.
    'paid_through',
])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * The public URL of the company logo, or null when none is uploaded.
     */
    public function logoUrl(): ?string
    {
        return TenantFileStore::url('logos', $this->logo_path);
    }

    /**
     * The colour this company's screens are painted in, as `#rrggbb`, or null
     * when it uses the house palette.
     *
     * Darkened only as far as white text on it demands — every dark surface in
     * the app carries some. See App\Support\BrandColor, which is the one place
     * that decides.
     */
    public function themeColor(): ?string
    {
        return $this->theme_color === null
            ? null
            : BrandColor::applied($this->theme_color);
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Tenant-scoped domain records.
     *
     * Every read of these tables goes through the team, so a query can only
     * ever see one company's rows. There is no global scope to forget to
     * apply and no `where('team_id', ...)` to leave off by accident.
     *
     * @return HasMany<Distributor, $this>
     */
    public function distributors(): HasMany
    {
        return $this->hasMany(Distributor::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * The people who work here — not the people who can log in. See Employee.
     *
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return HasMany<Holiday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /**
     * Wages leaving the company — the only table they leave by.
     *
     * FinancialReport::money() takes a single sum from here, which is what
     * makes double-counting salary structurally impossible. See the note on
     * ExpenseCategory::Salary.
     *
     * @return HasMany<SalaryPayment, $this>
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    /**
     * @return HasMany<EmployeeBonus, $this>
     */
    public function employeeBonuses(): HasMany
    {
        return $this->hasMany(EmployeeBonus::class);
    }

    /**
     * The company's own papers — licences, certificates, contracts.
     *
     * @return HasMany<CompanyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<Vendor, $this>
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    /**
     * @return HasMany<VendorBill, $this>
     */
    public function vendorBills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    /**
     * @return HasMany<VendorPayment, $this>
     */
    public function vendorPayments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    /**
     * @return HasMany<RawMaterial, $this>
     */
    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RawMaterial::class);
    }

    /**
     * @return HasMany<MaterialPurchase, $this>
     */
    public function materialPurchases(): HasMany
    {
        return $this->hasMany(MaterialPurchase::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<SalesReturn, $this>
     */
    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    /**
     * @return HasMany<Bank, $this>
     */
    public function banks(): HasMany
    {
        return $this->hasMany(Bank::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'suspended_at' => 'datetime',
            'suspension_mode' => SuspensionMode::class,
            'paid_through' => 'date',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
