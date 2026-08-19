<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\PayrollSetting;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Which days a company works.
 *
 * The **only** place that question is answered. The attendance grid tints its
 * columns from it, the summary counts against it, and payroll divides by it —
 * three screens that must agree about what March had in it, and would not for
 * long if each counted weekends itself.
 *
 * Nothing here is stored. A working day is derived from the company's weekend
 * setting and its holiday rows every time it is asked for, so moving a holiday
 * or switching to a six-day week corrects every month at once rather than
 * leaving already-computed ones stale. That is the same promise
 * App\Support\ProductStockReport makes about stock.
 *
 * Built once per month and reused, because payroll asks about thirty-one days
 * for every employee and a query per question would be thousands of them.
 */
final class WorkingCalendar
{
    /**
     * @param  array<int, int>  $weekendDays  ISO-8601 weekday numbers (Mon 1 … Sun 7)
     * @param  array<string, string>  $holidays  `Y-m-d` => name
     */
    private function __construct(
        private readonly array $weekendDays,
        private readonly array $holidays,
    ) {}

    /**
     * The calendar for one company over one span of dates.
     */
    public static function for(Team $team, CarbonInterface $from, CarbonInterface $to): self
    {
        $settings = PayrollSetting::forTeam($team);

        $holidays = $team->holidays()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->mapWithKeys(fn (Holiday $holiday) => [
                $holiday->date->toDateString() => $holiday->name,
            ])
            ->all();

        return new self(
            weekendDays: array_map('intval', $settings->weekend_days),
            holidays: $holidays,
        );
    }

    /**
     * The calendar for one company over one calendar month.
     */
    public static function forMonth(Team $team, CarbonInterface $month): self
    {
        return self::for(
            $team,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        );
    }

    /**
     * A day the company expects work to be done.
     *
     * Weekends and holidays are both "not worked", and the distinction only
     * matters for what the grid says in the cell — so callers that just need
     * the count ask this one question.
     */
    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $this->isWeekend($date) && ! $this->isHoliday($date);
    }

    public function isWeekend(CarbonInterface $date): bool
    {
        return in_array((int) $date->isoWeekday(), $this->weekendDays, true);
    }

    public function isHoliday(CarbonInterface $date): bool
    {
        return isset($this->holidays[$date->toDateString()]);
    }

    /**
     * Why a day is not worked, for the tooltip on a tinted column.
     */
    public function holidayName(CarbonInterface $date): ?string
    {
        return $this->holidays[$date->toDateString()] ?? null;
    }

    /**
     * How many working days fall between two dates, both ends included.
     *
     * This is payroll's divisor for a monthly salary: absence is worth a day of
     * the days actually worked, so a full month of attendance always pays
     * exactly the salary and no month can pay more than it.
     */
    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $count = 0;

        foreach ($this->days($from, $to) as $day) {
            if ($this->isWorkingDay($day)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Every date from one day to another, both ends included.
     *
     * @return list<Carbon>
     */
    public function days(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        $cursor = Carbon::parse($from->toDateString())->startOfDay();
        $last = Carbon::parse($to->toDateString())->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $days[] = $cursor->copy();
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * The days of a month that are not worked, as day-of-month numbers, for the
     * grid to tint.
     *
     * @return list<int>
     */
    public function nonWorkingDaysOfMonth(CarbonInterface $month): array
    {
        $days = [];

        foreach ($this->days($month->copy()->startOfMonth(), $month->copy()->endOfMonth()) as $day) {
            if (! $this->isWorkingDay($day)) {
                $days[] = (int) $day->day;
            }
        }

        return $days;
    }
}
