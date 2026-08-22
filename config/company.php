<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency Symbol
    |--------------------------------------------------------------------------
    |
    | v1 is single-currency per company (see the PRD's non-goals). This is a
    | platform-wide default and moves onto the teams table once per-company
    | settings ship.
    |
    */

    'currency_symbol' => env('COMPANY_CURRENCY_SYMBOL', '৳'),

    /*
    |--------------------------------------------------------------------------
    | File Storage Layout
    |--------------------------------------------------------------------------
    |
    | Uploaded files are namespaced per tenant so one company can never read
    | another's documents. Logos are public; invoice PDFs are private and must
    | be served through an authenticated controller route, never a direct URL.
    |
    | Files are named readably rather than by random hash, via
    | App\Support\StoredFileName: `name_prefix` plus a version, giving paths
    | like `logos/7/logo-2.png`. Every file type declares its prefix here so
    | the convention stays in one place as new ones are added.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Invoice Numbering
    |--------------------------------------------------------------------------
    |
    | Numbers are sequential per company, allocated under a Redis lock plus a
    | row lock — see App\Support\InvoiceNumbers. Set the starting number to
    | continue an existing series when a company migrates onto the platform.
    |
    */

    'invoices' => [
        'number_prefix' => env('INVOICE_NUMBER_PREFIX', 'INV'),
        'starting_number' => (int) env('INVOICE_STARTING_NUMBER', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sales Return Numbering
    |--------------------------------------------------------------------------
    |
    | Its own series, allocated the same way — see App\Support\ReturnNumbers.
    | Returns and invoices are numbered independently, so RNV1 and INV1 both
    | exist and neither series has gaps caused by the other.
    |
    */

    'returns' => [
        'number_prefix' => env('RETURN_NUMBER_PREFIX', 'RNV'),
        'starting_number' => (int) env('RETURN_STARTING_NUMBER', 1),
    ],

    'storage' => [

        'product_photos' => [
            'disk' => 'public',
            'path' => 'products',
            'name_prefix' => 'product',
            'max_kilobytes' => 2048,
        ],

        /*
         * Staff photos. Public like product photos rather than private: they
         * appear on the employee list and on a payslip, both of which are
         * rendered in the browser, and a signed route per thumbnail would be a
         * request each for no privacy gain — the path is unguessable and
         * namespaced per tenant.
         */
        'employee_photos' => [
            'disk' => 'public',
            'path' => 'employees',
            'name_prefix' => 'employee',
            'max_kilobytes' => 2048,
        ],

        'logos' => [
            'disk' => 'public',
            'path' => 'logos',
            'name_prefix' => 'logo',

            /*
             * The single source of truth for the logo size limit: the
             * validation rule, the browser-side pre-check, and the help text
             * all read this value. Keep it at or below PHP's
             * `upload_max_filesize`, or PHP rejects the file before validation
             * runs and the user gets a vaguer error than this limit produces.
             */
            'max_kilobytes' => 2048,
        ],

        /*
         * Company documents: trade licences, tax certificates, contracts.
         *
         * On the **private** disk, unlike logos and photos. A tax certificate
         * carries registration numbers and a bank mandate carries account
         * details; neither may sit behind a guessable public URL. Every read
         * goes through DocumentController::download, which checks the tenant
         * and the permission first.
         */
        'documents' => [
            'disk' => 'local',
            'path' => 'documents',
            'name_prefix' => 'document',

            /*
             * Larger than a photo, because these are scans — a multi-page
             * licence scanned at 300dpi runs to several megabytes, and a limit
             * that forces people to re-scan is a limit that makes them keep the
             * paper in a drawer instead.
             */
            'max_kilobytes' => 10240,
        ],

        // Reserved for the invoicing phase; nothing writes here yet.
        'invoices' => [
            'disk' => 'local',
            'path' => 'invoices',
            'name_prefix' => 'invoice',
        ],

    ],

];
