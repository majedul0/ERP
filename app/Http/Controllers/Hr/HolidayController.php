<?php

namespace App\Http\Controllers\Hr;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\PayrollSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company's working week, and the days off inside it.
 *
 * Both on one screen because they answer the same question — which days work is
 * expected — and both re-derive every month that has already passed, so a
 * holiday declared late corrects the attendance summary and the next payroll
 * run rather than leaving a stale figure behind.
 */
class HolidayController extends Controller
{
    use ResolvesCurrentTeam;

    /** ISO-8601 weekday numbers, for the settings form. */
    private const WEEKDAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $settings = PayrollSetting::forTeam($team);
        $year = (int) $request->integer('year', (int) Carbon::now()->year);

        return Inertia::render('company/hr/holidays/index', [
            'year' => $year,
            'weekdays' => array_map(
                fn (int $number, string $name) => ['value' => $number, 'label' => $name],
                array_keys(self::WEEKDAYS),
                self::WEEKDAYS,
            ),
            'weekendDays' => array_map('intval', $settings->weekend_days),
            'overtimeHourlyRate' => $settings->overtime_hourly_rate,
            'holidays' => $team->holidays()
                ->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $holiday) => [
                    'id' => $holiday->id,
                    'date' => $holiday->date->toDateString(),
                    'name' => $holiday->name,
                ])
                ->all(),
        ]);
    }

    /**
     * Save the working week.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            // An empty array is legitimate — a company that works every day.
            'weekend_days' => ['present', 'array', 'max:7'],
            'weekend_days.*' => ['integer', 'min:1', 'max:7'],
            'overtime_hourly_rate' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ]);

        PayrollSetting::updateOrCreate(
            ['team_id' => $team->id],
            [
                'weekend_days' => array_values(array_unique(array_map(
                    'intval',
                    $validated['weekend_days'],
                ))),
                'overtime_hourly_rate' => $validated['overtime_hourly_rate'] ?? null,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Working week saved. Past months are recounted from it.'),
        ]);

        return to_route('holidays.index', ['current_team' => $team->slug]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('holidays', 'date')->where('team_id', $team->id),
            ],
            'name' => ['required', 'string', 'max:120'],
        ], [
            'date.unique' => __('That day is already a holiday.'),
        ]);

        $team->holidays()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holiday added.')]);

        return to_route('holidays.index', [
            'current_team' => $team->slug,
            'year' => Carbon::parse($validated['date'])->year,
        ]);
    }

    /**
     * Remove a holiday.
     *
     * Hard, not soft: a row that lingers keeps a working day out of payroll
     * while looking deleted, and there is nothing here worth recovering.
     */
    public function destroy(Request $request, string $current_team, Holiday $holiday): RedirectResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($holiday->team_id === $team->id, 404);

        $year = $holiday->date->year;
        $holiday->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holiday removed.')]);

        return to_route('holidays.index', [
            'current_team' => $team->slug,
            'year' => $year,
        ]);
    }
}
