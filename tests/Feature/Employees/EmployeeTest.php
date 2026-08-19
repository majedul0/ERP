<?php

namespace Tests\Feature\Employees;

use App\Enums\SalaryType;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeTest extends TestCase
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_code' => 'EMP-001',
            'name' => 'Rahim Uddin',
            'father_name' => 'Karim Uddin',
            'phone' => '01711111111',
            'designation' => 'Delivery Supervisor',
            'salary_type' => SalaryType::Monthly->value,
            'joined_on' => '2026-01-15',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function store(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)->post(
            route('employees.store', ['current_team' => $this->team->slug]),
            $this->payload($overrides),
        );
    }

    /**
     * A member of staff with exactly the permissions named, and nothing else.
     *
     * @param  array<int, TeamPermission>  $permissions
     */
    private function memberWith(array $permissions): User
    {
        $member = User::factory()->create();

        $this->team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
            'permissions' => array_map(
                fn (TeamPermission $permission) => $permission->value,
                $permissions,
            ),
        ]);

        $member->switchTeam($this->team);

        return $member;
    }

    public function test_an_employee_can_be_added()
    {
        $this->store()->assertRedirect(
            route('employees.index', ['current_team' => $this->team->slug]),
        )->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();

        $this->assertSame('Rahim Uddin', $employee->name);
        $this->assertSame('EMP-001', $employee->employee_code);
        $this->assertSame(SalaryType::Monthly, $employee->salary_type);
        $this->assertSame($this->team->id, $employee->team_id);
        $this->assertSame($this->user->id, $employee->created_by);
        $this->assertTrue($employee->isActive());
    }

    /**
     * The balance is derived from payroll and payments, so a new employee owes
     * and is owed nothing — and nothing on this form can set it.
     */
    public function test_a_new_employee_starts_with_a_zero_balance()
    {
        $this->store(['balance' => 50_000])->assertSessionHasNoErrors();

        $this->assertSame(0, Employee::firstOrFail()->balance);
    }

    public function test_a_staff_number_is_unique_within_the_company_only()
    {
        $this->store()->assertSessionHasNoErrors();
        $this->store(['name' => 'Someone Else'])->assertSessionHasErrors('employee_code');

        $this->assertSame(1, Employee::count());

        // Another company may number its own first hire the same way.
        $other = User::factory()->create();

        $this->actingAs($other)->post(
            route('employees.store', ['current_team' => $other->currentTeam->slug]),
            $this->payload(),
        )->assertSessionHasNoErrors();

        $this->assertSame(2, Employee::count());
    }

    public function test_a_leaving_date_cannot_precede_the_joining_date()
    {
        $this->store(['left_on' => '2025-12-01'])->assertSessionHasErrors('left_on');

        $this->assertSame(0, Employee::count());
    }

    /**
     * A leaving date is how somebody leaves — the record stays, so past
     * payslips and their account survive.
     */
    public function test_recording_a_leaving_date_keeps_the_record()
    {
        $this->store()->assertSessionHasNoErrors();
        $employee = Employee::firstOrFail();

        $this->actingAs($this->user)->put(
            route('employees.update', [
                'current_team' => $this->team->slug,
                'employee' => $employee->id,
            ]),
            $this->payload(['left_on' => '2026-06-30']),
        )->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertFalse($employee->isActive());
        $this->assertNotNull($employee->left_on);
        $this->assertNull($employee->deleted_at);
    }

    public function test_employment_dates_decide_which_days_are_counted()
    {
        $employee = Employee::factory()->create([
            'team_id' => $this->team->id,
            'joined_on' => '2026-03-10',
            'left_on' => '2026-03-20',
        ]);

        $this->assertFalse($employee->wasEmployedOn(now()->parse('2026-03-09')));
        // Both ends inclusive: they worked the day they joined and were owed
        // for the day they left.
        $this->assertTrue($employee->wasEmployedOn(now()->parse('2026-03-10')));
        $this->assertTrue($employee->wasEmployedOn(now()->parse('2026-03-20')));
        $this->assertFalse($employee->wasEmployedOn(now()->parse('2026-03-21')));
    }

    public function test_a_photo_is_stored_and_replaced_without_reusing_the_name()
    {
        Storage::fake('public');

        $this->actingAs($this->user)->post(
            route('employees.store', ['current_team' => $this->team->slug]),
            [...$this->payload(), 'photo' => UploadedFile::fake()->image('rahim.jpg')],
        )->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();
        $first = $employee->photo_path;

        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->user)->put(
            route('employees.update', [
                'current_team' => $this->team->slug,
                'employee' => $employee->id,
            ]),
            [...$this->payload(), 'photo' => UploadedFile::fake()->image('new.jpg')],
        )->assertSessionHasNoErrors();

        $second = $employee->fresh()->photo_path;

        // A new path every time, so a cached copy of the old one cannot mask
        // the replacement — and the old file is gone.
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_a_department_from_another_company_is_rejected()
    {
        $foreign = Department::factory()->create();

        $this->store(['department_id' => $foreign->id])
            ->assertSessionHasErrors('department_id');

        $this->assertSame(0, Employee::count());
    }

    public function test_another_companys_employee_is_not_found()
    {
        $foreign = Employee::factory()->create();

        $this->actingAs($this->user)
            ->get(route('employees.show', [
                'current_team' => $this->team->slug,
                'employee' => $foreign->id,
            ]))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->put(route('employees.update', [
                'current_team' => $this->team->slug,
                'employee' => $foreign->id,
            ]), $this->payload())
            ->assertNotFound();
    }

    public function test_the_list_only_shows_this_companys_employees()
    {
        Employee::factory()->create(['team_id' => $this->team->id, 'name' => 'Ours']);
        Employee::factory()->create(['name' => 'Theirs']);

        $this->actingAs($this->user)
            ->get(route('employees.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/hr/employees/index')
                ->has('employees', 1)
                ->where('employees.0.name', 'Ours')
                ->where('activeCount', 1),
            );
    }

    /**
     * The registry is readable with `employee:view`; what somebody is paid sits
     * behind `payroll:view`. The keys are omitted, not hidden — a prop the
     * browser receives has been disclosed whatever the page does with it.
     */
    public function test_the_registry_discloses_no_salary_figures()
    {
        Employee::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->user)
            ->get(route('employees.index', ['current_team' => $this->team->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('employees.0.balance')
                ->missing('employees.0.salary')
                ->missing('employees.0.rate'),
            );
    }

    public function test_a_viewer_can_read_but_not_write()
    {
        $viewer = $this->memberWith([TeamPermission::ViewEmployees]);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['current_team' => $this->team->slug]))
            ->assertOk();

        $this->actingAs($viewer)->post(
            route('employees.store', ['current_team' => $this->team->slug]),
            $this->payload(),
        )->assertForbidden();

        $this->assertSame(0, Employee::count());
    }

    /**
     * A salesperson has no business reading a colleague's record.
     */
    public function test_a_member_has_no_access_by_default()
    {
        $member = User::factory()->create();
        $this->team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($this->team);

        $this->actingAs($member)
            ->get(route('employees.index', ['current_team' => $this->team->slug]))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login()
    {
        $this->get(route('employees.index', ['current_team' => $this->team->slug]))
            ->assertRedirect(route('login'));
    }

    public function test_departments_are_scoped_and_named_once()
    {
        $this->actingAs($this->user)->post(
            route('departments.store', ['current_team' => $this->team->slug]),
            ['name' => 'Delivery'],
        )->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post(
            route('departments.store', ['current_team' => $this->team->slug]),
            ['name' => 'Delivery'],
        )->assertSessionHasErrors('name');

        $this->assertSame(1, Department::where('team_id', $this->team->id)->count());
    }

    /**
     * Departments soft-delete, so the table carries no unique index — the name
     * of one removed last year must be usable again.
     */
    public function test_a_removed_department_name_can_be_used_again()
    {
        $department = Department::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Packaging',
        ]);

        $this->actingAs($this->user)->delete(route('departments.destroy', [
            'current_team' => $this->team->slug,
            'department' => $department->id,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post(
            route('departments.store', ['current_team' => $this->team->slug]),
            ['name' => 'Packaging'],
        )->assertSessionHasNoErrors();

        $this->assertSame(1, Department::where('team_id', $this->team->id)->count());
    }

    /**
     * Reorganisations are not redundancies.
     */
    public function test_removing_a_department_leaves_its_people_employed()
    {
        $department = Department::factory()->create(['team_id' => $this->team->id]);
        $employee = Employee::factory()->create([
            'team_id' => $this->team->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($this->user)->delete(route('departments.destroy', [
            'current_team' => $this->team->slug,
            'department' => $department->id,
        ]))->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertNull($employee->deleted_at);
        // The id is kept, but a soft-deleted department reads as none.
        $this->assertNull($employee->department);
    }
}
