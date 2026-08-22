<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\SaveCompanyDocument;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\SaveDocumentRequest;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * The company's papers, the ones needing attention first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $documents = $team->documents()
            ->with('uploader')
            ->get()
            /*
             * Sorted by urgency rather than by date: an expired licence belongs
             * at the top of the list whatever it is called or when it was
             * filed, and somebody opening this screen is usually asking "is
             * anything about to lapse" rather than "what did we upload last".
             */
            ->sortBy([
                fn (CompanyDocument $a, CompanyDocument $b) => $a->status()->urgency() <=> $b->status()->urgency(),
                fn (CompanyDocument $a, CompanyDocument $b) => ($a->expires_on->timestamp ?? PHP_INT_MAX)
                    <=> ($b->expires_on->timestamp ?? PHP_INT_MAX),
            ])
            ->values();

        return Inertia::render('company/documents/index', [
            'documents' => $documents->map(fn (CompanyDocument $document) => $this->present($document))->all(),
            'categories' => DocumentCategory::options(),
            'summary' => [
                'total' => $documents->count(),
                'expired' => $documents->filter(fn (CompanyDocument $d) => $d->status() === DocumentStatus::Expired)->count(),
                'expiring' => $documents->filter(fn (CompanyDocument $d) => $d->status() === DocumentStatus::Expiring)->count(),
                'warningDays' => DocumentStatus::WARNING_DAYS,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('company/documents/create', [
            'categories' => DocumentCategory::options(),
        ]);
    }

    public function store(SaveDocumentRequest $request, SaveCompanyDocument $saveDocument): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $document = $saveDocument->handle(
            team: $team,
            data: $request->safe()->except('file'),
            file: $request->file('file'),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title filed.', ['title' => $document->title]),
        ]);

        return to_route('documents.index', ['current_team' => $team->slug]);
    }

    /**
     * One document, with every version it has had.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function show(Request $request, string $current_team, CompanyDocument $document): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($document->team_id === $team->id, 404);

        $document->load(['uploader', 'versions']);

        return Inertia::render('company/documents/show', [
            'document' => [
                ...$this->present($document),
                'note' => $document->note,
                'versions' => $document->versions
                    ->map(fn (CompanyDocumentVersion $version) => [
                        'id' => $version->id,
                        'version' => $version->version,
                        'originalName' => $version->original_name,
                        'sizeBytes' => $version->size_bytes,
                        'supersededAt' => $version->superseded_at?->toDateString(),
                    ])
                    ->all(),
            ],
        ]);
    }

    public function edit(Request $request, string $current_team, CompanyDocument $document): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($document->team_id === $team->id, 404);

        return Inertia::render('company/documents/edit', [
            'document' => [
                ...$this->present($document),
                'note' => $document->note,
            ],
            'categories' => DocumentCategory::options(),
        ]);
    }

    public function update(
        SaveDocumentRequest $request,
        string $current_team,
        CompanyDocument $document,
        SaveCompanyDocument $saveDocument,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($document->team_id === $team->id, 404);

        $saveDocument->handle(
            team: $team,
            data: $request->safe()->except('file'),
            document: $document,
            file: $request->file('file'),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->hasFile('file')
                ? __('A new version was filed.')
                : __('Document updated.'),
        ]);

        return to_route('documents.show', [
            'current_team' => $team->slug,
            'document' => $document->id,
        ]);
    }

    public function destroy(
        Request $request,
        string $current_team,
        CompanyDocument $document,
        SaveCompanyDocument $saveDocument,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($document->team_id === $team->id, 404);

        $saveDocument->delete($document);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document removed.')]);

        return to_route('documents.index', ['current_team' => $team->slug]);
    }

    /**
     * Stream the file.
     *
     * The **only** way a stored document is ever read. Files live on the
     * private disk precisely so that this method — which checks the tenant and
     * has already been through the permission middleware — is the sole route to
     * them; there is no URL to guess and none to leak.
     *
     * `?version=` reaches a superseded file, which is how last year's licence
     * stays available after this year's replaced it.
     */
    public function download(
        Request $request,
        string $current_team,
        CompanyDocument $document,
    ): StreamedResponse {
        $team = $this->currentTeam($request);

        abort_unless($document->team_id === $team->id, 404);

        $validated = $request->validate([
            'version' => ['nullable', 'integer', 'min:1'],
            'inline' => ['nullable', 'boolean'],
        ]);

        $path = $document->file_path;
        $name = $document->original_name;
        $mime = $document->mime_type;
        $inlineAllowed = $document->isInlineViewable();

        if (isset($validated['version']) && (int) $validated['version'] !== $document->version) {
            $version = $document->versions()
                ->where('version', (int) $validated['version'])
                ->firstOrFail();

            $path = $version->file_path;
            $name = $version->original_name;
            $mime = $version->mime_type;
            $inlineAllowed = in_array($mime, CompanyDocument::INLINE_TYPES, true);
        }

        $disk = Storage::disk((string) config('company.storage.documents.disk'));

        abort_unless($disk->exists($path), 404);

        /*
         * Inline only for the allowlist. `Content-Disposition: inline` on an
         * uploaded SVG or HTML file would run its script in this app's own
         * origin, so anything outside the list is sent as an attachment
         * whatever it claims to be — and `nosniff` stops the browser making its
         * own mind up about the type either way.
         */
        $inline = ($validated['inline'] ?? false) && $inlineAllowed;

        return $disk->download($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                .'; filename="'.addslashes($name).'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CompanyDocument $document): array
    {
        $status = $document->status();

        return [
            'id' => $document->id,
            'title' => $document->title,
            'category' => $document->category->value,
            'categoryLabel' => $document->category->label(),
            'reference' => $document->reference,
            'issuedOn' => $document->issued_on?->toDateString(),
            'expiresOn' => $document->expires_on?->toDateString(),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'needsAttention' => $status->needsAttention(),
            'daysUntilExpiry' => $document->daysUntilExpiry(),
            'originalName' => $document->original_name,
            'mimeType' => $document->mime_type,
            'sizeBytes' => $document->size_bytes,
            'version' => $document->version,
            'canPreview' => $document->isInlineViewable(),
            'uploadedBy' => $document->uploader?->name,
            'uploadedAt' => $document->created_at?->toDateString(),
        ];
    }
}
