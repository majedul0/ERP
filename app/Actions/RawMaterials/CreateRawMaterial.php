<?php

namespace App\Actions\RawMaterials;

use App\Enums\MaterialUnit;
use App\Models\RawMaterial;
use App\Models\Team;
use App\Support\Money;

class CreateRawMaterial
{
    /**
     * Register a raw material for a company.
     *
     * The opening stock typed on the form is what is on the shelf today. Every
     * later movement goes through a purchase or a recount, so this is the only
     * place a quantity appears from nowhere.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): RawMaterial
    {
        return RawMaterial::create([
            'team_id' => $team->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'unit' => MaterialUnit::from($data['unit']),
            'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
            'reorder_level' => (int) ($data['reorder_level'] ?? 0),
            'unit_cost' => Money::fromInput($data['unit_cost'] ?? 0),
            'note' => $data['note'] ?? null,
        ]);
    }
}
