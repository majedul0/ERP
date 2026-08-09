<?php

namespace App\Http\Controllers\RawMaterials;

use App\Actions\RawMaterials\CreateRawMaterial;
use App\Actions\RawMaterials\UpdateRawMaterial;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\MaterialUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\RawMaterials\SaveRawMaterialRequest;
use App\Models\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RawMaterialController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * List the company's raw materials.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/raw-materials/index', [
            'materials' => $team->rawMaterials()
                ->orderBy('name')
                ->get()
                ->map(fn (RawMaterial $material) => $this->present($material))
                ->all(),
        ]);
    }

    /**
     * Show the registration form.
     */
    public function create(): Response
    {
        return Inertia::render('company/raw-materials/create', [
            'units' => MaterialUnit::options(),
        ]);
    }

    /**
     * Register a new material.
     */
    public function store(SaveRawMaterialRequest $request, CreateRawMaterial $createRawMaterial): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $createRawMaterial->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Material added.')]);

        return to_route('materials.index', ['current_team' => $team->slug]);
    }

    /**
     * Show the edit form.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function edit(Request $request, string $current_team, RawMaterial $material): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($material->team_id === $team->id, 404);

        return Inertia::render('company/raw-materials/edit', [
            'material' => $this->present($material),
            'units' => MaterialUnit::options(),
        ]);
    }

    /**
     * Save the changes.
     */
    public function update(
        SaveRawMaterialRequest $request,
        string $current_team,
        RawMaterial $material,
        UpdateRawMaterial $updateRawMaterial,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($material->team_id === $team->id, 404);

        $updateRawMaterial->handle($material, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Material updated.')]);

        return to_route('materials.index', ['current_team' => $team->slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(RawMaterial $material): array
    {
        return [
            'id' => $material->id,
            'name' => $material->name,
            'code' => $material->code,
            'unit' => $material->unit->value,
            'unitShort' => $material->unit->short(),
            'stockQuantity' => $material->stock_quantity,
            'reorderLevel' => $material->reorder_level,
            'unitCost' => $material->unit_cost,
            'stockValue' => $material->stockValue(),
            'isLow' => $material->isLow(),
            'note' => $material->note,
        ];
    }
}
