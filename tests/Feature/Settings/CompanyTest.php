<?php

namespace Tests\Feature\Settings;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_company_settings()
    {
        $this->get(route('company.edit'))->assertRedirect(route('login'));
    }

    public function test_owners_can_view_company_settings()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('company.edit'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/company')
            ->where('company.name', $user->currentTeam->name)
            ->where('company.logoUrl', null)
            ->where('canUpdate', true),
        );
    }

    public function test_owners_can_rename_the_company()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('company.update'), ['name' => 'Ocean Consumer Products']);

        $response->assertRedirect(route('company.edit'));

        $this->assertDatabaseHas('teams', [
            'id' => $user->currentTeam->id,
            'name' => 'Ocean Consumer Products',
        ]);
    }

    public function test_the_address_and_phone_printed_on_documents_can_be_set()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('company.update'), [
            'name' => 'Ocean Consumer Products',
            'address' => 'Uttara, Dhaka, Bangladesh',
            'phone' => '01712-932814',
        ]);

        $response->assertRedirect(route('company.edit'));

        $team = $user->currentTeam->fresh();

        $this->assertSame('Uttara, Dhaka, Bangladesh', $team->address);
        $this->assertSame('01712-932814', $team->phone);
    }

    public function test_the_address_and_phone_reach_every_page_as_shared_props()
    {
        $user = User::factory()->create();
        $user->currentTeam->update([
            'address' => 'Uttara, Dhaka, Bangladesh',
            'phone' => '01712-932814',
        ]);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyBrand.address', 'Uttara, Dhaka, Bangladesh')
                ->where('companyBrand.phone', '01712-932814'),
            );
    }

    public function test_the_address_and_phone_are_optional()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('company.update'), ['name' => 'Ocean Consumer Products'])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->currentTeam->fresh()->address);
    }

    public function test_renaming_the_company_regenerates_its_slug()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('company.update'), ['name' => 'Ocean Consumer Products']);

        $this->assertSame(
            'ocean-consumer-products',
            $user->currentTeam->fresh()->slug,
        );
    }

    public function test_the_company_name_is_required()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('company.update'), ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_members_cannot_rename_the_company()
    {
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $response = $this
            ->actingAs($member)
            ->patch(route('company.update'), ['name' => 'Hijacked Ltd']);

        $response->assertForbidden();
        $this->assertNotSame('Hijacked Ltd', $team->fresh()->name);
    }

    public function test_owners_can_upload_a_logo()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 512, 512),
        ]);

        $response->assertRedirect(route('company.edit'));

        $this->assertSame("logos/{$team->id}/logo-1.png", $team->fresh()->logo_path);
        Storage::disk('public')->assertExists("logos/{$team->id}/logo-1.png");
    }

    public function test_uploading_a_logo_replaces_and_deletes_the_previous_file()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);

        $this->assertSame("logos/{$team->id}/logo-1.png", $team->fresh()->logo_path);

        $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);

        // A new name, so a cached copy of the old one is never served in its
        // place, and the superseded file does not linger on disk.
        $this->assertSame("logos/{$team->id}/logo-2.png", $team->fresh()->logo_path);
        Storage::disk('public')->assertMissing("logos/{$team->id}/logo-1.png");
        Storage::disk('public')->assertExists("logos/{$team->id}/logo-2.png");
    }

    public function test_a_replacement_in_another_format_keeps_its_own_extension()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);

        $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $this->assertSame("logos/{$team->id}/logo-2.jpg", $team->fresh()->logo_path);
        Storage::disk('public')->assertMissing("logos/{$team->id}/logo-1.png");
    }

    public function test_logos_are_stored_in_a_per_company_folder()
    {
        Storage::fake('public');

        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('a.png'),
        ]);

        $this->actingAs($second)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('b.png'),
        ]);

        // Same readable filename, different company directory — the folder is
        // what keeps one tenant's uploads away from another's.
        $this->assertSame(
            "logos/{$first->currentTeam->id}/logo-1.png",
            $first->currentTeam->fresh()->logo_path,
        );
        $this->assertSame(
            "logos/{$second->currentTeam->id}/logo-1.png",
            $second->currentTeam->fresh()->logo_path,
        );
        $this->assertNotSame(
            $first->currentTeam->id,
            $second->currentTeam->id,
        );
    }

    public function test_oversized_logos_are_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400)->size(3000),
        ]);

        $response->assertSessionHasErrors([
            'logo' => 'The logo must be 2 MB or smaller.',
        ]);
        $this->assertNull($user->currentTeam->fresh()->logo_path);
    }

    public function test_the_size_limit_comes_from_config()
    {
        Storage::fake('public');
        config(['company.storage.logos.max_kilobytes' => 5120]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400)->size(3000),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNotNull($user->currentTeam->fresh()->logo_path);
    }

    public function test_the_page_ships_the_size_limit_to_the_browser()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('company.edit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('maxLogoKilobytes', 2048),
        );
    }

    /**
     * PHP can reject an upload before validation runs. Laravel's default
     * message ("The logo failed to upload.") names neither cause, so it is
     * replaced by one that distinguishes them — see CompanyLogoRequest.
     */
    public function test_an_oversized_php_upload_names_the_size_limit()
    {
        $message = $this->uploadFailureMessageFor(UPLOAD_ERR_INI_SIZE);

        $this->assertStringNotContainsString('failed to upload', $message);
        $this->assertStringContainsString('too large', $message);
        $this->assertStringContainsString('2 MB', $message);
    }

    public function test_a_server_side_upload_failure_does_not_blame_the_file_size()
    {
        $message = $this->uploadFailureMessageFor(UPLOAD_ERR_NO_TMP_DIR);

        $this->assertStringNotContainsString('failed to upload', $message);
        $this->assertStringNotContainsString('too large', $message);
        $this->assertStringContainsString('temporary directory', $message);
    }

    /**
     * Post a logo that PHP flagged with the given upload error and return the
     * message the user is shown.
     */
    private function uploadFailureMessageFor(int $uploadError): string
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => new UploadedFile(
                path: UploadedFile::fake()->image('logo.png')->getPathname(),
                originalName: 'logo.png',
                mimeType: 'image/png',
                error: $uploadError,
                test: false,
            ),
        ]);

        $response->assertSessionHasErrors('logo');
        $this->assertNull($user->currentTeam->fresh()->logo_path);

        return session('errors')->first('logo');
    }

    public function test_non_image_uploads_are_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->create('payload.pdf', 20, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('logo');
        $this->assertNull($user->currentTeam->fresh()->logo_path);
    }

    public function test_members_cannot_upload_a_logo()
    {
        Storage::fake('public');

        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $response = $this->actingAs($member)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertForbidden();
        $this->assertNull($team->fresh()->logo_path);
    }

    public function test_owners_can_remove_the_logo()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $path = $team->fresh()->logo_path;

        $response = $this->actingAs($user)->delete(route('company.logo.destroy'));

        $response->assertRedirect(route('company.edit'));
        $this->assertNull($team->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_logo_url_is_shared_with_every_page()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('company.logo.update'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        // A fresh instance, because actingAs() reuses the model between
        // requests and would serve the relation loaded before the upload.
        $response = $this->actingAs($user->fresh())->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->whereNot('companyBrand.logoUrl', null),
        );
    }
}
