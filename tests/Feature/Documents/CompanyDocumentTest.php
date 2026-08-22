<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function file(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)->post(
            route('documents.store', ['current_team' => $this->team->slug]),
            array_merge([
                'title' => 'Trade Licence 2026',
                'category' => DocumentCategory::Licence->value,
                'reference' => 'TL-998877',
                'issued_on' => '2026-01-01',
                'expires_on' => '2026-12-31',
                'file' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
            ], $overrides),
        );
    }

    /**
     * @param  array<int, TeamPermission>  $permissions
     */
    private function memberWith(array $permissions): User
    {
        $member = User::factory()->create();

        $this->team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
            'permissions' => array_map(
                fn (TeamPermission $permission) => $permission->value,
                $permissions,
            ),
        ]);

        $member->switchTeam($this->team);

        return $member;
    }

    public function test_a_document_can_be_filed()
    {
        $this->file()->assertRedirect(
            route('documents.index', ['current_team' => $this->team->slug]),
        )->assertSessionHasNoErrors();

        $document = CompanyDocument::firstOrFail();

        $this->assertSame('Trade Licence 2026', $document->title);
        $this->assertSame(DocumentCategory::Licence, $document->category);
        $this->assertSame($this->team->id, $document->team_id);
        $this->assertSame($this->user->id, $document->uploaded_by);
        $this->assertSame(1, $document->version);
        $this->assertSame('licence.pdf', $document->original_name);

        // The dates are stored as given. Asserting this is the whole point:
        // without it, a value silently resolved to today looked like a pass.
        $this->assertSame('2026-01-01', $document->issued_on->toDateString());
        $this->assertSame('2026-12-31', $document->expires_on->toDateString());
    }

    /**
     * The bug this rule exists for.
     *
     * Laravel's `date` rule runs `strtotime`, which accepts `22`, `2026`,
     * `now` and whitespace — and every one of them resolves to **today**. A
     * half-typed expiry was therefore filed as expiring this morning, with
     * nothing on screen to say the date had been changed.
     */
    public function test_a_partial_date_is_refused_rather_than_read_as_today()
    {
        $this->travelTo('2026-08-22');

        foreach (['22', '2026', 'now', 'today', '31/12/2027', '2026-8'] as $loose) {
            $this->file(['expires_on' => $loose])
                ->assertSessionHasErrors('expires_on');
        }

        $this->assertSame(0, CompanyDocument::count(), 'None of those should have been filed.');
    }

    /**
     * Whitespace is not an error — the framework trims it to nothing before
     * validation runs, and nothing means no expiry. What matters is that it
     * does not become today, which is what Carbon would have made of it.
     */
    public function test_whitespace_reads_as_no_expiry_rather_than_today()
    {
        $this->travelTo('2026-08-22');

        $this->file(['expires_on' => '   '])->assertSessionHasNoErrors();

        $this->assertNull(CompanyDocument::firstOrFail()->expires_on);
    }

    public function test_an_impossible_date_is_refused()
    {
        // A rollover date: `createFromFormat` would quietly make this 1 March.
        $this->file(['expires_on' => '2027-02-30'])
            ->assertSessionHasErrors('expires_on');

        $this->assertSame(0, CompanyDocument::count());
    }

    /**
     * Editing something else must not disturb a date the form did not carry.
     *
     * Absent and cleared were treated as the same thing, so saving a title
     * wiped the renewal date without a word.
     */
    public function test_editing_without_the_date_field_leaves_the_date_alone()
    {
        $this->file()->assertSessionHasNoErrors();
        $document = CompanyDocument::firstOrFail();

        $this->actingAs($this->user)->put(
            route('documents.update', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]),
            ['title' => 'Trade Licence 2026 (renamed)', 'category' => $document->category->value],
        )->assertSessionHasNoErrors();

        $document->refresh();

        $this->assertSame('Trade Licence 2026 (renamed)', $document->title);
        $this->assertSame('2026-12-31', $document->expires_on->toDateString());
        $this->assertSame('2026-01-01', $document->issued_on->toDateString());
    }

    /**
     * Clearing it deliberately still works — an empty field is a real answer,
     * and it means "this one never expires".
     */
    public function test_clearing_the_date_removes_it()
    {
        $this->file()->assertSessionHasNoErrors();
        $document = CompanyDocument::firstOrFail();

        $this->actingAs($this->user)->put(
            route('documents.update', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]),
            [
                'title' => $document->title,
                'category' => $document->category->value,
                'expires_on' => '',
            ],
        )->assertSessionHasNoErrors();

        $this->assertNull($document->fresh()->expires_on);
        $this->assertSame(
            DocumentStatus::Permanent,
            $document->fresh()->status(),
        );
    }

    /**
     * An expiry with no issue date is ordinary — the comparison rule must not
     * fire against a field that was never given.
     */
    public function test_an_expiry_without_an_issue_date_is_accepted()
    {
        $this->file(['issued_on' => '', 'expires_on' => '2027-06-30'])
            ->assertSessionHasNoErrors();

        $document = CompanyDocument::firstOrFail();

        $this->assertNull($document->issued_on);
        $this->assertSame('2027-06-30', $document->expires_on->toDateString());
    }

    /**
     * A tax certificate carries registration numbers. It goes on the private
     * disk, where the only way to it is a route that checks the tenant.
     */
    public function test_the_file_is_stored_privately_and_not_publicly()
    {
        $this->file()->assertSessionHasNoErrors();

        $document = CompanyDocument::firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);

        // And the stored name is ours, not the uploader's.
        $this->assertStringNotContainsString('licence.pdf', $document->file_path);
        $this->assertStringContainsString("documents/{$this->team->id}/", $document->file_path);
    }

    public function test_a_file_is_required_when_filing_but_not_when_editing()
    {
        $this->file(['file' => null])->assertSessionHasErrors('file');
        $this->assertSame(0, CompanyDocument::count());

        $this->file()->assertSessionHasNoErrors();
        $document = CompanyDocument::firstOrFail();

        // Correcting the expiry date should not mean finding the scan again.
        $this->actingAs($this->user)->put(
            route('documents.update', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]),
            [
                'title' => $document->title,
                'category' => $document->category->value,
                'expires_on' => '2027-06-30',
            ],
        )->assertSessionHasNoErrors();

        $document->refresh();

        $this->assertSame('2027-06-30', $document->expires_on->toDateString());
        $this->assertSame(1, $document->version, 'The version must not move without a new file.');
    }

    /**
     * Renewing a licence does not make last year's copy worthless — it is what
     * proves the company was licensed last year.
     */
    public function test_replacing_the_file_keeps_the_old_one_as_a_version()
    {
        $this->file()->assertSessionHasNoErrors();

        $document = CompanyDocument::firstOrFail();
        $firstPath = $document->file_path;

        $this->actingAs($this->user)->put(
            route('documents.update', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]),
            [
                'title' => 'Trade Licence 2027',
                'category' => DocumentCategory::Licence->value,
                'expires_on' => '2027-12-31',
                'file' => UploadedFile::fake()->create('renewed.pdf', 220, 'application/pdf'),
            ],
        )->assertSessionHasNoErrors();

        $document->refresh();

        $this->assertSame(2, $document->version);
        $this->assertNotSame($firstPath, $document->file_path);

        $version = CompanyDocumentVersion::firstOrFail();

        $this->assertSame(1, $version->version);
        $this->assertSame($firstPath, $version->file_path);

        // Both files are still on disk — the old one is not shredded.
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_an_earlier_version_can_still_be_downloaded()
    {
        $this->file()->assertSessionHasNoErrors();
        $document = CompanyDocument::firstOrFail();

        $this->actingAs($this->user)->put(
            route('documents.update', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]),
            [
                'title' => $document->title,
                'category' => $document->category->value,
                'file' => UploadedFile::fake()->create('renewed.pdf', 220, 'application/pdf'),
            ],
        )->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('documents.download', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
                'version' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="licence.pdf"');
    }

    public function test_a_document_can_be_downloaded_and_viewed()
    {
        $this->file()->assertSessionHasNoErrors();
        $document = CompanyDocument::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('documents.download', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="licence.pdf"')
            ->assertHeader('x-content-type-options', 'nosniff');

        // A PDF may be rendered rather than downloaded.
        $this->actingAs($this->user)
            ->get(route('documents.download', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
                'inline' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename="licence.pdf"');
    }

    /**
     * `Content-Disposition: inline` on an uploaded document that the browser
     * treats as markup would run its script in this app's origin, so anything
     * outside the allowlist is sent as an attachment however it is asked for.
     */
    public function test_a_non_previewable_type_is_never_served_inline()
    {
        $this->file([
            'title' => 'Supplier agreement',
            'category' => DocumentCategory::Contract->value,
            'file' => UploadedFile::fake()->create('agreement.docx', 50,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasNoErrors();

        $document = CompanyDocument::firstOrFail();

        $this->assertFalse($document->isInlineViewable());

        $this->actingAs($this->user)
            ->get(route('documents.download', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
                'inline' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="agreement.docx"');
    }

    public function test_another_companys_document_cannot_be_reached()
    {
        $foreign = CompanyDocument::factory()->create();

        foreach (['documents.show', 'documents.edit', 'documents.download'] as $route) {
            $this->actingAs($this->user)
                ->get(route($route, [
                    'current_team' => $this->team->slug,
                    'document' => $foreign->id,
                ]))
                ->assertNotFound();
        }

        $this->actingAs($this->user)
            ->delete(route('documents.destroy', [
                'current_team' => $this->team->slug,
                'document' => $foreign->id,
            ]))
            ->assertNotFound();
    }

    // ------------------------------------------------------------- expiry

    public function test_expiry_status_is_derived_from_the_date()
    {
        $this->travelTo('2026-08-20');

        $permanent = CompanyDocument::factory()->permanent()->make();
        $expired = CompanyDocument::factory()->expired()->make();
        $expiring = CompanyDocument::factory()->expiringSoon()->make();
        $valid = CompanyDocument::factory()->make(['expires_on' => '2027-06-30']);

        $this->assertSame(DocumentStatus::Permanent, $permanent->status());
        $this->assertSame(DocumentStatus::Expired, $expired->status());
        $this->assertSame(DocumentStatus::Expiring, $expiring->status());
        $this->assertSame(DocumentStatus::Valid, $valid->status());

        $this->assertTrue($expired->status()->needsAttention());
        $this->assertFalse($valid->status()->needsAttention());
    }

    /**
     * Nothing about the status is stored, so the same row reads differently
     * tomorrow — which is the whole point of not storing it.
     */
    public function test_a_valid_document_becomes_expiring_as_the_date_approaches()
    {
        $document = CompanyDocument::factory()->create([
            'team_id' => $this->team->id,
            'expires_on' => '2026-09-30',
        ]);

        $this->travelTo('2026-08-01');
        $this->assertSame(DocumentStatus::Valid, $document->status());

        $this->travelTo('2026-09-15');
        $this->assertSame(DocumentStatus::Expiring, $document->fresh()->status());

        $this->travelTo('2026-10-01');
        $this->assertSame(DocumentStatus::Expired, $document->fresh()->status());
    }

    public function test_the_list_leads_with_what_needs_attention()
    {
        $this->travelTo('2026-08-20');

        CompanyDocument::factory()->create([
            'team_id' => $this->team->id,
            'title' => 'Fine',
            'expires_on' => '2027-12-31',
        ]);
        CompanyDocument::factory()->permanent()->create([
            'team_id' => $this->team->id,
            'title' => 'Forever',
        ]);
        CompanyDocument::factory()->expired()->create([
            'team_id' => $this->team->id,
            'title' => 'Lapsed',
        ]);
        CompanyDocument::factory()->expiringSoon()->create([
            'team_id' => $this->team->id,
            'title' => 'Due',
        ]);

        $this->actingAs($this->user)
            ->get(route('documents.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/documents/index')
                ->where('documents.0.title', 'Lapsed')
                ->where('documents.1.title', 'Due')
                ->where('summary.expired', 1)
                ->where('summary.expiring', 1)
                ->where('summary.total', 4),
            );
    }

    public function test_an_expiry_before_the_issue_date_is_rejected()
    {
        $this->file([
            'issued_on' => '2026-06-01',
            'expires_on' => '2026-01-01',
        ])->assertSessionHasErrors('expires_on');

        $this->assertSame(0, CompanyDocument::count());
    }

    public function test_an_executable_upload_is_refused()
    {
        $this->file([
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, CompanyDocument::count());
    }

    // -------------------------------------------------------- permissions

    public function test_a_viewer_can_read_and_download_but_not_write()
    {
        $this->file()->assertSessionHasNoErrors();
        $document = CompanyDocument::firstOrFail();

        $viewer = $this->memberWith([TeamPermission::ViewDocuments]);

        $this->actingAs($viewer)
            ->get(route('documents.index', ['current_team' => $this->team->slug]))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('documents.download', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('documents.create', ['current_team' => $this->team->slug]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('documents.destroy', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]))
            ->assertForbidden();
    }

    /**
     * The vault is its own permission, not a corner of company settings — a
     * manager who can rename the company has no business in the bank mandates.
     */
    public function test_company_settings_access_does_not_open_the_vault()
    {
        $this->file()->assertSessionHasNoErrors();

        $member = $this->memberWith([TeamPermission::UpdateTeam]);

        $this->actingAs($member)
            ->get(route('documents.index', ['current_team' => $this->team->slug]))
            ->assertForbidden();
    }

    public function test_a_member_has_no_access_by_default()
    {
        $member = User::factory()->create();
        $this->team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
        ]);
        $member->switchTeam($this->team);

        $this->actingAs($member)
            ->get(route('documents.index', ['current_team' => $this->team->slug]))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login()
    {
        $this->get(route('documents.index', ['current_team' => $this->team->slug]))
            ->assertRedirect(route('login'));
    }

    public function test_removing_a_document_is_soft_and_keeps_the_files()
    {
        $this->file()->assertSessionHasNoErrors();
        $document = CompanyDocument::firstOrFail();
        $path = $document->file_path;

        $this->actingAs($this->user)
            ->delete(route('documents.destroy', [
                'current_team' => $this->team->slug,
                'document' => $document->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($document);

        // A delete that shreds the only copy of a trade licence is not
        // something to offer behind one confirm dialog.
        Storage::disk('local')->assertExists($path);
    }
}
