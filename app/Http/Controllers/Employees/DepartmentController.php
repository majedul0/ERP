<?php

namespace App\Http\Controllers\Employees;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\SaveDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company's own list of departments.
 *
 * Small enough to live on one screen: the list, an add box, and a rename in
 * place. There is no create page and no show page, because a department is a
 * name and nothing else.
 */
class DepartmentController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/hr/departments/index', [
            'departments' => $team->departments()
                ->withCount('employees')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'employeeCount' => (int) $department->employees_count,
                ])
                ->all(),
        ]);
    }

    public function store(SaveDepartmentRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $team->departments()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department added.')]);

        return to_route('departments.index', ['current_team' => $team->slug]);
    }

    public function update(
        SaveDepartmentRequest $request,
        string $current_team,
        Department $department,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($department->team_id === $team->id, 404);

        $department->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department renamed.')]);

        return to_route('departments.index', ['current_team' => $team->slug]);
    }

    /**
     * Remove a department.
     *
     * Soft, so its employees are untouched — they keep the id, and because
     * Department soft-deletes, `$employee->department` simply reads as none.
     * Reorganisations are not redundancies, and nothing about a person's record
     * should change because the box around them was renamed.
     *
     * (The `nullOnDelete` on the foreign key is the backstop for a hard delete,
     * which only a company deletion performs.)
     */
    public function destroy(Request $request, string $current_team, Department $department): RedirectResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($department->team_id === $team->id, 404);

        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department removed.')]);

        return to_route('departments.index', ['current_team' => $team->slug]);
    }
}
