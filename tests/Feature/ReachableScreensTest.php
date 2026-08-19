<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every screen an owner can reach actually answers.
 *
 * This exists because of a real production failure: `distributors/create` was
 * registered *after* `distributors/{distributor}`, so the literal `create` was
 * matched as a distributor id. Postgres rejected the cast and the company got a
 * 500 the moment they tried to add a distributor.
 *
 * Two things made it invisible until then. Nothing tested a create screen — the
 * suite exercised `store`, which is a different URL — and the tests run on
 * sqlite, which answers a mistyped id with an empty result and a 404 rather
 * than the error Postgres raises. So the ordering bug would have shown as a
 * 404 here even before it reached a real database.
 *
 * The names are read from the router rather than listed by hand, so a new
 * module is covered the day its routes are added instead of the day somebody
 * remembers to extend this file.
 */
class ReachableScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
    }

    /**
     * Every `*.create` screen that needs nothing but the company itself.
     *
     * @return array<string, array{string}>
     */
    public static function createScreens(): array
    {
        return [
            'distributors' => ['distributors.create'],
            'products' => ['products.create'],
            'invoices' => ['invoices.create'],
            'sales returns' => ['returns.create'],
            'payments' => ['payments.record'],
            'vendors' => ['vendors.create'],
            'vendor bills' => ['bills.create'],
            'vendor payments' => ['vendor-payments.create'],
            'expenses' => ['expenses.create'],
            'raw materials' => ['materials.create'],
            'employees' => ['employees.create'],
            'salary payments' => ['salary-payments.create'],
            'material purchases' => ['purchases.create'],
        ];
    }

    #[DataProvider('createScreens')]
    public function test_a_create_screen_is_not_matched_as_a_record_id(string $routeName): void
    {
        $this->actingAs($this->user)
            ->get(route($routeName, ['current_team' => $this->team->slug]))
            ->assertOk();
    }

    /**
     * The list screens, which is where somebody lands before pressing Add.
     *
     * @return array<string, array{string}>
     */
    public static function listScreens(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'distributors' => ['distributors.index'],
            'products' => ['products.index'],
            'invoices' => ['invoices.index'],
            'sales returns' => ['returns.index'],
            'payments' => ['payments.index'],
            'banks' => ['banks.index'],
            'vendors' => ['vendors.index'],
            'vendor bills' => ['bills.index'],
            'vendor payments' => ['vendor-payments.index'],
            'expenses' => ['expenses.index'],
            'reports' => ['reports.index'],
            'stock report' => ['stock-reports.index'],
            'raw materials' => ['materials.index'],
            'material purchases' => ['purchases.index'],
            'stock levels' => ['stock-levels.index'],
            'employees' => ['employees.index'],
            'departments' => ['departments.index'],
            'attendance' => ['attendance.index'],
            'attendance summary' => ['attendance.summary'],
            'holidays' => ['holidays.index'],
            'payroll' => ['payroll.index'],
            'salary rates' => ['salary-rates.index'],
            'salary payments' => ['salary-payments.index'],
            'bonuses' => ['bonuses.index'],
        ];
    }

    #[DataProvider('listScreens')]
    public function test_a_list_screen_answers(string $routeName): void
    {
        $this->actingAs($this->user)
            ->get(route($routeName, ['current_team' => $this->team->slug]))
            ->assertOk();
    }
}
