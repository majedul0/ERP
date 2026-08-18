<?php

namespace Tests\Feature\Settings;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Support\BrandColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyThemeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_a_company_starts_on_the_house_palette()
    {
        $this->actingAs($this->user)
            ->get(route('company.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/company')
                ->where('company.themeColor', null)
                ->where('company.usesDefaultTheme', true)
                // The picker is prefilled with what they can already see.
                ->where('company.themeRgb.red', 139)
                ->where('company.themeRgb.green', 98)
                ->where('company.themeRgb.blue', 68),
            );
    }

    public function test_a_colour_is_saved_as_the_rgb_it_was_entered_as()
    {
        $this->actingAs($this->user)->patch(route('company.theme.update'), [
            'red' => 12,
            'green' => 74,
            'blue' => 110,
        ])->assertRedirect(route('company.edit'))->assertSessionHasNoErrors();

        $this->assertSame('#0c4a6e', $this->team->fresh()->theme_color);
    }

    /**
     * The colour has to reach every screen, not just the settings page — it is
     * shared, so switching company repaints without a page load.
     */
    public function test_the_colour_is_shared_with_every_page()
    {
        $this->team->update(['theme_color' => '#0c4a6e']);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['current_team' => $this->team->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyBrand.themeColor', '#0c4a6e'),
            );
    }

    /**
     * Every dark surface in the app carries white text, so a colour too pale to
     * hold it is darkened rather than refused — the hue is theirs, the
     * legibility is not negotiable.
     */
    public function test_a_colour_too_pale_for_white_text_is_darkened_when_applied()
    {
        $pale = '#ffe14d';

        $this->team->update(['theme_color' => $pale]);

        $applied = $this->team->fresh()->themeColor();

        $this->assertNotSame($pale, $applied);
        $this->assertGreaterThanOrEqual(4.5, $this->contrastWithWhite($applied));

        // What they chose is still what is stored.
        $this->assertSame($pale, $this->team->fresh()->theme_color);
    }

    public function test_a_colour_dark_enough_already_is_left_alone()
    {
        $this->team->update(['theme_color' => '#0c4a6e']);

        $this->assertSame('#0c4a6e', $this->team->fresh()->themeColor());
    }

    public function test_the_default_palette_carries_white_text()
    {
        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrastWithWhite(BrandColor::DEFAULT),
        );
    }

    public function test_resetting_returns_the_company_to_the_house_palette()
    {
        $this->team->update(['theme_color' => '#0c4a6e']);

        $this->actingAs($this->user)
            ->patch(route('company.theme.update'), ['reset' => true])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->team->fresh()->theme_color);
    }

    public function test_a_channel_outside_the_range_is_rejected()
    {
        $this->actingAs($this->user)->patch(route('company.theme.update'), [
            'red' => 300,
            'green' => 74,
            'blue' => 110,
        ])->assertSessionHasErrors('red');

        $this->assertNull($this->team->fresh()->theme_color);
    }

    /**
     * Changing what everybody in the company looks at all day is an admin act,
     * the same as renaming it.
     */
    public function test_a_member_cannot_repaint_the_company()
    {
        $member = User::factory()->create();
        $this->team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($this->team);

        $this->actingAs($member)->patch(route('company.theme.update'), [
            'red' => 12,
            'green' => 74,
            'blue' => 110,
        ])->assertForbidden();

        $this->assertNull($this->team->fresh()->theme_color);
    }

    /**
     * WCAG's contrast ratio between a colour and white.
     */
    private function contrastWithWhite(string $hex): float
    {
        ['red' => $red, 'green' => $green, 'blue' => $blue] = BrandColor::toRgb($hex);

        $linear = static fn (int $channel): float => ($value = $channel / 255) <= 0.04045
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;

        $luminance = 0.2126 * $linear($red) + 0.7152 * $linear($green) + 0.0722 * $linear($blue);

        return 1.05 / ($luminance + 0.05);
    }
}
