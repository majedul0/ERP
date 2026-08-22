<?php

namespace App\Actions\Documents;

use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SaveCompanyDocument
{
    /**
     * File a document, or change one already filed.
     *
     * The detail worth stating: **replacing the file keeps the old one.**
     * Renewing a trade licence does not make last year's copy worthless — it is
     * what proves the company was licensed last year, and an auditor asking
     * about a past period wants exactly that. The superseded file moves to
     * `company_document_versions` and stays downloadable; only deleting the
     * whole document takes it out of reach.
     *
     * Editing the details without choosing a file leaves the file alone, so
     * correcting an expiry date does not mean finding the scan again.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(
        Team $team,
        array $data,
        ?CompanyDocument $document = null,
        ?UploadedFile $file = null,
        ?User $actor = null,
    ): CompanyDocument {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($team, $data, $document, $file, $actor, &$storedPath): CompanyDocument {
                $attributes = [
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'reference' => $data['reference'] ?? null,
                    'note' => $data['note'] ?? null,
                ];

                /*
                 * A date the caller did not send is left exactly as it was.
                 *
                 * `$data['expires_on'] ?? null` treated "the form did not carry
                 * this field" and "the person cleared it" as the same thing, so
                 * editing a title through any form without a date input wiped
                 * the renewal date without a word.
                 */
                foreach (['issued_on', 'expires_on'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $attributes[$field] = self::asDate($data[$field]);
                    }
                }

                if ($document === null) {
                    if ($file === null) {
                        // The form request already requires it on create; this
                        // is the guard for any other caller.
                        throw new InvalidArgumentException('A new document needs a file.');
                    }

                    $storedPath = $this->store($team, $file, previousPath: null, title: $attributes['title']);

                    return CompanyDocument::create([
                        ...$attributes,
                        ...$this->fileAttributes($file, $storedPath, version: 1),
                        'team_id' => $team->id,
                        'uploaded_by' => $actor?->id,
                    ]);
                }

                /*
                 * The previous path is read from the locked row, not from the
                 * model handed in — two people replacing at once would
                 * otherwise each archive the other's file.
                 */
                $document = CompanyDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

                if ($file !== null) {
                    // Keep what is being replaced, then take the new version.
                    CompanyDocumentVersion::create([
                        'company_document_id' => $document->id,
                        'uploaded_by' => $document->uploaded_by,
                        'version' => $document->version,
                        'file_path' => $document->file_path,
                        'original_name' => $document->original_name,
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                        'superseded_at' => now(),
                    ]);

                    $storedPath = $this->store(
                        $team,
                        $file,
                        previousPath: $document->file_path,
                        title: $attributes['title'],
                    );

                    $attributes = [
                        ...$attributes,
                        ...$this->fileAttributes($file, $storedPath, version: $document->version + 1),
                        'uploaded_by' => $actor?->id,
                    ];
                }

                $document->update($attributes);

                return $document;
            });
        } catch (Throwable $e) {
            // Nothing committed, so the file just written has no owner.
            TenantFileStore::delete('documents', $storedPath);

            throw $e;
        }
    }

    /**
     * Remove a document and every file it has ever had.
     *
     * The row soft-deletes so a mistake is recoverable, but the files are left
     * on disk for the same reason — a delete that shreds the only copy of a
     * trade licence is not something to offer behind one confirm dialog. They
     * are removed by the storage prune, not here.
     */
    public function delete(CompanyDocument $document): void
    {
        $document->delete();
    }

    /**
     * Write the upload and return its disk-relative path.
     *
     * Named by the document's title so a directory listing is readable —
     * `document-trade-licence-2.pdf` rather than a hash. The uploader's own
     * filename never reaches the path; it is kept only to name the download.
     */
    private function store(Team $team, UploadedFile $file, ?string $previousPath, string $title): string
    {
        return TenantFileStore::put(
            'documents',
            $team->id,
            $file,
            previousPath: $previousPath,
            nameSuffix: $title,
        );
    }

    /**
     * A calendar date, or null — and never today by accident.
     *
     * The model's `date` cast hands anything it is given to Carbon, which
     * resolves `22`, `now` and whitespace to the current moment rather than
     * refusing them. Parsing to an exact format here, and checking the result
     * round-trips, means a value that is not a real date reaches the database
     * as an error rather than as this morning.
     */
    private static function asDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            $date = null;
        }

        // `createFromFormat` rolls a bad date over rather than failing — the
        // 32nd of a month becomes the 1st of the next — so the only way to
        // know it was real is that it comes back unchanged.
        if ($date === null || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("[{$value}] is not a calendar date.");
        }

        return $date->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    private function fileAttributes(UploadedFile $file, string $path, int $version): array
    {
        return [
            'file_path' => $path,
            // Trimmed to the column, and only ever used as a download name.
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'version' => $version,
        ];
    }
}
