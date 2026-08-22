<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\CompanyDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyDocument>
 */
class CompanyDocumentFactory extends Factory
{
    protected $model = CompanyDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => 'Trade Licence',
            'category' => DocumentCategory::Licence,
            'reference' => 'TL-'.fake()->numerify('######'),
            'issued_on' => '2026-01-01',
            'expires_on' => '2026-12-31',
            'file_path' => 'documents/1/document-trade-licence-1.pdf',
            'original_name' => 'trade-licence.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 120_000,
            'version' => 1,
        ];
    }

    /** A paper that never lapses, like an incorporation certificate. */
    public function permanent(): self
    {
        return $this->state(fn () => ['expires_on' => null]);
    }

    /** Already lapsed. */
    public function expired(): self
    {
        return $this->state(fn () => [
            'expires_on' => now()->subDays(10)->toDateString(),
        ]);
    }

    /** Inside the warning window. */
    public function expiringSoon(): self
    {
        return $this->state(fn () => [
            'expires_on' => now()->addDays(10)->toDateString(),
        ]);
    }
}
