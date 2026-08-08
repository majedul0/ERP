<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyLogoRequest extends FormRequest
{
    /**
     * Only a member who may update the company may change its branding.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->currentTeam;

        return $team !== null && $user->can('update', $team);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The `image` rule verifies actual image content rather than trusting the
     * client's content type, and excludes SVG — which can carry script.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.$this->maxKilobytes(),
                'dimensions:max_width=2000,max_height=2000',
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.uploaded' => $this->uploadFailureMessage(),
            'logo.max' => __('The logo must be :limit MB or smaller.', ['limit' => $this->maxMegabytes()]),
            'logo.mimes' => __('The logo must be a JPG, PNG, or WebP image.'),
            'logo.dimensions' => __('The logo must be at most 2000x2000 pixels.'),
        ];
    }

    /**
     * Explain a failure that happened inside PHP, before validation ran.
     *
     * Laravel's default here is "The logo failed to upload." — true, but it
     * names neither of the two very different causes. An oversized file is the
     * user's to fix; anything else (a missing temp directory, an unwritable
     * one, a truncated request) is the server's, and saying "too large" for
     * those sends people off rescaling an image that was never the problem.
     */
    private function uploadFailureMessage(): string
    {
        $error = $this->file('logo')?->getError();

        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return __('The logo is too large for the server to accept. The limit is :limit MB, and this server currently stops uploads above :php.', [
                'limit' => $this->maxMegabytes(),
                'php' => ini_get('upload_max_filesize'),
            ]);
        }

        return __('The logo could not be uploaded — the server rejected it: :reason', [
            'reason' => $this->file('logo')?->getErrorMessage() ?? __('unknown error'),
        ]);
    }

    /**
     * The configured maximum logo size, in whole megabytes.
     */
    private function maxMegabytes(): int
    {
        return (int) round($this->maxKilobytes() / 1024);
    }

    /**
     * The configured maximum logo size, in kilobytes.
     */
    private function maxKilobytes(): int
    {
        return (int) config('company.storage.logos.max_kilobytes');
    }
}
