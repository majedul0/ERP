<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDocumentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'reference' => ['nullable', 'string', 'max:120'],

            /*
             * `date_format:Y-m-d`, not `date`.
             *
             * Laravel's `date` rule runs `strtotime`, which accepts far more
             * than a date: `22`, `2026`, `now`, whitespace — and every one of
             * those resolves to **today**. Combined with the model's `date`
             * cast, which parses just as loosely, a half-typed or malformed
             * value was silently stored as the current date instead of being
             * refused, so a licence expiring in 2027 could be filed as expiring
             * this morning with nothing on screen to say so.
             *
             * A date input submits `Y-m-d` and nothing else, so demanding
             * exactly that turns every one of those values into an error the
             * person can see.
             */
            'issued_on' => ['nullable', 'date_format:Y-m-d'],

            /*
             * A renewal that predates the issue is a typo, not a fact — and one
             * worth catching here rather than as a licence that reads as
             * expired the day it was filed.
             *
             * The comparison is only applied when an issue date was actually
             * given: `after_or_equal` against an absent field falls back to
             * reading the field *name* as a date, which fails for reasons
             * nobody could act on.
             */
            'expires_on' => array_filter([
                'nullable',
                'date_format:Y-m-d',
                filled($this->input('issued_on')) ? 'after_or_equal:issued_on' : null,
            ]),

            'note' => ['nullable', 'string', 'max:2000'],

            /*
             * Required on create, optional on update: editing the expiry date
             * of a licence should not mean re-uploading the scan.
             *
             * The mime list is an allowlist of what an office actually files. A
             * `mimes:` rule checks the file's real type rather than the name it
             * arrived with, so renaming a script to `.pdf` does not get it in.
             */
            'file' => [
                $this->routeDocument() === null ? 'required' : 'nullable',
                'file',
                'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx',
                'max:'.(int) config('company.storage.documents.max_kilobytes'),
            ],
        ];
    }

    /**
     * The document being edited, or null on create.
     */
    private function routeDocument(): mixed
    {
        return $this->route('document');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Choose the file to store.'),
            'file.mimes' => __('Upload a PDF, an image, or an Office document.'),
            'file.max' => __('The file is larger than :size MB.', [
                'size' => (int) config('company.storage.documents.max_kilobytes') / 1024,
            ]),
            'expires_on.after_or_equal' => __('The expiry date cannot be before the issue date.'),
            'issued_on.date_format' => __('Enter the issue date as a full date.'),
            'expires_on.date_format' => __('Enter the expiry date as a full date.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'issued_on' => 'issue date',
            'expires_on' => 'expiry date',
            'reference' => 'reference number',
        ];
    }
}
