<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The company's papers: trade licence, TIN and BIN certificates, bank
         * mandates, insurance, tenancy agreements.
         *
         * Kept on the **private** disk and served through an authenticated
         * controller route — never a public URL. A tax certificate carries a
         * company's registration numbers, and a guessable link to one is a
         * disclosure whatever the odds of guessing it.
         *
         * The column that earns this table its keep is `expires_on`. Almost
         * every document here renews, and the thing that actually hurts a
         * business is discovering a licence lapsed three weeks ago — so the
         * date is a first-class field and the screens are built around it.
         */
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');

            // See App\Enums\DocumentCategory. A string so a stale row keeps its
            // meaning if the enum grows.
            $table->string('category');

            // The licence or certificate number as printed on the paper, so a
            // person can match the record to the document without opening it.
            $table->string('reference')->nullable();

            $table->date('issued_on')->nullable();

            /*
             * Null means it does not expire — a company incorporation
             * certificate does not. Nullable rather than a far-future date so
             * "has no expiry" and "expires in 2099" stay different facts.
             */
            $table->date('expires_on')->nullable();

            $table->text('note')->nullable();

            // The current file. `original_name` is kept only to name the
            // download; the stored path never uses it — see StoredFileName.
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            // Bumped when the file is replaced, so the screen can say "version
            // 3" without counting rows.
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'category']);
            // "What is about to lapse" is the question this table is asked most.
            $table->index(['team_id', 'expires_on']);
        });

        /*
         * Superseded files.
         *
         * Renewing a trade licence does not make last year's copy worthless —
         * it is what proves the company was licensed last year, and an auditor
         * asking about a past period wants exactly that. Replacing a file moves
         * the old one here rather than deleting it.
         */
        Schema::create('company_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('version');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            // When this version stopped being current.
            $table->timestamp('superseded_at')->nullable();

            $table->timestamps();

            $table->unique(['company_document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_versions');
        Schema::dropIfExists('company_documents');
    }
};
