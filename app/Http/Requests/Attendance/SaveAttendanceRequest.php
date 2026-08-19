<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SaveAttendanceRequest extends FormRequest
{
    /** How many cells one save may carry. */
    public const MAX_MARKS = 2000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $daysInMonth = $this->daysInMonth();

        return [
            // `YYYY-MM`. The month is stated once rather than repeated on every
            // mark, so a payload cannot straddle two of them.
            'month' => ['required', 'string', 'date_format:Y-m'],

            /*
             * Only the cells that changed. A whole-month PUT would be N×31 rows
             * and, worse, would clobber a colleague editing a different
             * employee in the same grid — a diff cannot.
             */
            'marks' => ['present', 'array', 'max:'.self::MAX_MARKS],

            'marks.*.employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],

            // A day number, not a date: it is smaller, and it makes a mark
            // landing in the wrong month structurally impossible.
            'marks.*.day' => ['required', 'integer', 'min:1', 'max:'.$daysInMonth],

            // Null clears the cell. See AttendanceRecord: no row is a state.
            'marks.*.status' => ['nullable', Rule::enum(AttendanceStatus::class)],

            'marks.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The month being marked, as its first day.
     */
    public function month(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->string('month').'-01')->startOfMonth();
    }

    /**
     * How long the month actually is, so the 31st of a 30-day month is refused
     * rather than silently rolling into the next one.
     */
    private function daysInMonth(): int
    {
        $month = $this->input('month');

        if (! is_string($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            // The `date_format` rule reports the real problem; 31 keeps this
            // rule from throwing on the way there.
            return 31;
        }

        return (int) Carbon::createFromFormat('Y-m-d', $month.'-01')->daysInMonth;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marks.max' => __('Too many changes to save at once. Save what you have and continue.'),
            'marks.*.day.max' => __('That day does not exist in this month.'),
        ];
    }
}
