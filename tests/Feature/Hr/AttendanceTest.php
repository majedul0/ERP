<?php

namespace Tests\Feature\Hr;

use App\Enums\AttendanceStatus;
use App\Enums\SalaryType;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollSetting;
use App\Models\Team;
use App\Models\User;
use App\Support\WorkingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->employee = Employee::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Rahim Uddin',
            'joined_on' => '2025-01-01',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     */
    private function save(array $marks, string $month = '2026-08'): TestResponse
    {
        return $this->actingAs($this->user)->put(
            route('attendance.update', ['current_team' => $this->team->slug]),
            ['month' => $month, 'marks' => $marks],
        );
    }

    private function grid(string $month = '2026-08'): TestResponse
    {
        return $this->actingAs($this->user)->get(route('attendance.index', [
            'current_team' => $this->team->slug,
            'month' => $month,
        ]));
    }

    public function test_a_mark_is_saved_against_the_right_day()
    {
        $this->save([
            ['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent'],
        ])->assertSessionHasNoErrors();

        $record = AttendanceRecord::firstOrFail();

        $this->assertSame('2026-08-03', $record->date->toDateString());
        $this->assertSame(AttendanceStatus::Absent, $record->status);
        $this->assertSame($this->team->id, $record->team_id);
        $this->assertSame($this->user->id, $record->marked_by);
    }

    public function test_saving_the_same_cell_twice_changes_it_rather_than_duplicating()
    {
        $this->save([['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent']]);
        $this->save([['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'present']]);

        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame(AttendanceStatus::Present, AttendanceRecord::firstOrFail()->status);
    }

    /**
     * "No mark" is a state the grid must be able to return to, so clearing a
     * cell deletes the row rather than soft-deleting it.
     */
    public function test_clearing_a_cell_removes_the_row()
    {
        $this->save([['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent']]);
        $this->assertSame(1, AttendanceRecord::count());

        $this->save([['employee_id' => $this->employee->id, 'day' => 3, 'status' => null]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AttendanceRecord::count());

        // And the day can be marked again — a lingering row would hold the
        // unique index against it.
        $this->save([['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'present']])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceRecord::count());
    }

    /**
     * Clearing two different people's different days must not clear the cells
     * where those rows and columns cross.
     *
     * Deleting `whereIn(employee) AND whereIn(date)` is a cross product: it
     * wiped four cells when asked to clear two.
     */
    public function test_clearing_cells_touches_only_the_cells_named()
    {
        $other = Employee::factory()->create([
            'team_id' => $this->team->id,
            'joined_on' => '2025-01-01',
        ]);

        $this->save([
            ['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 7, 'status' => 'absent'],
            ['employee_id' => $other->id, 'day' => 3, 'status' => 'absent'],
            ['employee_id' => $other->id, 'day' => 7, 'status' => 'present'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, AttendanceRecord::count());

        // Clear one cell from each person, on different days.
        $this->save([
            ['employee_id' => $this->employee->id, 'day' => 3, 'status' => null],
            ['employee_id' => $other->id, 'day' => 7, 'status' => null],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, AttendanceRecord::count());

        // The two survivors are the crossings — exactly what a cross-product
        // delete would have taken.
        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $this->employee->id,
            'date' => '2026-08-07',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $other->id,
            'date' => '2026-08-03',
        ]);
    }

    /**
     * Only the cells somebody touched are submitted, so two supervisors working
     * the same month cannot save over each other.
     */
    public function test_a_save_leaves_cells_it_was_not_told_about_alone()
    {
        $this->save([
            ['employee_id' => $this->employee->id, 'day' => 4, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 5, 'status' => 'absent'],
        ]);

        $this->save([['employee_id' => $this->employee->id, 'day' => 6, 'status' => 'half_day']]);

        $this->assertSame(3, AttendanceRecord::count());
        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $this->employee->id,
            'date' => '2026-08-05',
            'status' => 'absent',
        ]);
    }

    public function test_a_day_outside_the_month_is_rejected()
    {
        // September has 30 days.
        $this->save(
            [['employee_id' => $this->employee->id, 'day' => 31, 'status' => 'present']],
            '2026-09',
        )->assertSessionHasErrors('marks.0.day');

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_another_companys_employee_cannot_be_marked()
    {
        $foreign = Employee::factory()->create();

        $this->save([['employee_id' => $foreign->id, 'day' => 3, 'status' => 'present']])
            ->assertSessionHasErrors('marks.0.employee_id');

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_the_grid_only_offers_days_somebody_was_employed_for()
    {
        Employee::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Mid Month Joiner',
            'joined_on' => '2026-08-11',
        ]);

        $this->grid()->assertOk()->assertInertia(function (Assert $page) {
            /** @var list<array<string, mixed>> $employees */
            $employees = $page->toArray()['props']['employees'];
            $joiner = collect($employees)->firstWhere('name', 'Mid Month Joiner');

            $this->assertSame(11, $joiner['firstDay']);
            $this->assertSame(31, $joiner['lastDay']);
        });
    }

    public function test_somebody_who_left_before_the_month_is_not_shown()
    {
        Employee::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Gone',
            'joined_on' => '2025-01-01',
            'left_on' => '2026-07-15',
        ]);

        $this->grid()->assertInertia(fn (Assert $page) => $page
            ->component('company/hr/attendance/index')
            ->has('employees', 1)
            ->where('employees.0.name', 'Rahim Uddin'),
        );
    }

    /**
     * Friday and Saturday by default, which is the Bangladeshi week.
     */
    public function test_the_default_weekend_is_friday_and_saturday()
    {
        $calendar = WorkingCalendar::forMonth($this->team, Carbon::create(2026, 8, 1));

        // 2026-08-07 is a Friday, the 8th a Saturday, the 9th a Sunday.
        $this->assertTrue($calendar->isWeekend(Carbon::create(2026, 8, 7)));
        $this->assertTrue($calendar->isWeekend(Carbon::create(2026, 8, 8)));
        $this->assertFalse($calendar->isWeekend(Carbon::create(2026, 8, 9)));
    }

    public function test_a_holiday_is_not_a_working_day()
    {
        Holiday::factory()->create([
            'team_id' => $this->team->id,
            'date' => '2026-08-12',
            'name' => 'Eid',
        ]);

        $calendar = WorkingCalendar::forMonth($this->team, Carbon::create(2026, 8, 1));

        $this->assertFalse($calendar->isWorkingDay(Carbon::create(2026, 8, 12)));
        $this->assertSame('Eid', $calendar->holidayName(Carbon::create(2026, 8, 12)));
    }

    /**
     * The working week is a setting, not a mark on each day, so changing it
     * re-derives every month that has already passed.
     */
    public function test_changing_the_weekend_recounts_a_past_month()
    {
        $before = WorkingCalendar::forMonth($this->team, Carbon::create(2026, 8, 1))
            ->workingDaysBetween(Carbon::create(2026, 8, 1), Carbon::create(2026, 8, 31));

        PayrollSetting::updateOrCreate(
            ['team_id' => $this->team->id],
            ['weekend_days' => [5]],
        );

        $after = WorkingCalendar::forMonth($this->team->fresh(), Carbon::create(2026, 8, 1))
            ->workingDaysBetween(Carbon::create(2026, 8, 1), Carbon::create(2026, 8, 31));

        // August 2026 has five Saturdays; dropping them from the weekend adds
        // five working days to a month that had already been counted.
        $this->assertSame($before + 5, $after);
    }

    public function test_the_summary_counts_the_month()
    {
        $this->save([
            ['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 4, 'status' => 'absent'],
            ['employee_id' => $this->employee->id, 'day' => 5, 'status' => 'half_day'],
            ['employee_id' => $this->employee->id, 'day' => 6, 'status' => 'paid_leave'],
        ]);

        $this->actingAs($this->user)
            ->get(route('attendance.summary', [
                'current_team' => $this->team->slug,
                'month' => '2026-08',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/hr/attendance/summary')
                ->where('summary.rows.0.present', 1)
                ->where('summary.rows.0.absent', 1)
                ->where('summary.rows.0.halfDays', 1)
                ->where('summary.rows.0.paidLeave', 1),
            );
    }

    /**
     * An unmarked day is normal for salaried staff and costly for daily-wage
     * ones, so the summary counts it rather than hiding it.
     */
    public function test_unmarked_working_days_are_counted()
    {
        $daily = Employee::factory()->dailyWage()->create([
            'team_id' => $this->team->id,
            'joined_on' => '2025-01-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('attendance.summary', [
                'current_team' => $this->team->slug,
                'month' => '2026-08',
            ]))
            ->assertInertia(function (Assert $page) use ($daily) {
                /** @var array<string, mixed> $summary */
                $summary = $page->toArray()['props']['summary'];
                $row = collect($summary['rows'])->firstWhere('id', $daily->id);

                // Nothing marked at all, so every working day is unmarked.
                $this->assertSame($summary['workingDays'], $row['unmarked']);
                $this->assertSame(0, $row['present']);
            });
    }

    public function test_the_summary_downloads_as_a_spreadsheet()
    {
        $response = $this->actingAs($this->user)->get(route('attendance.excel', [
            'current_team' => $this->team->slug,
            'month' => '2026-08',
        ]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Rahim Uddin', $response->streamedContent());
    }

    public function test_a_viewer_can_read_but_not_mark()
    {
        $viewer = User::factory()->create();

        $this->team->memberships()->create([
            'user_id' => $viewer->id,
            'role' => TeamRole::Member,
            'permissions' => [TeamPermission::ViewAttendance->value],
        ]);

        $viewer->switchTeam($this->team);

        $this->actingAs($viewer)
            ->get(route('attendance.index', ['current_team' => $this->team->slug]))
            ->assertOk();

        $this->actingAs($viewer)->put(
            route('attendance.update', ['current_team' => $this->team->slug]),
            ['month' => '2026-08', 'marks' => [
                ['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'present'],
            ]],
        )->assertForbidden();

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_the_grid_only_shows_this_companys_marks()
    {
        $foreign = Employee::factory()->create();

        AttendanceRecord::factory()->create([
            'team_id' => $foreign->team_id,
            'employee_id' => $foreign->id,
            'date' => '2026-08-03',
        ]);

        $this->grid()->assertInertia(fn (Assert $page) => $page
            ->has('employees', 1)
            ->where('marks', []),
        );
    }

    /**
     * Salaried staff are marked by exception and daily-wage staff are not — the
     * enum is the only place that difference is written down.
     */
    public function test_unmarked_days_count_differently_by_salary_type()
    {
        $this->assertTrue(SalaryType::Monthly->unmarkedDayCountsAsWorked());
        $this->assertFalse(SalaryType::Daily->unmarkedDayCountsAsWorked());
    }
}
