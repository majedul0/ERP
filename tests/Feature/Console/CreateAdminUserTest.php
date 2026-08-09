<?php

namespace Tests\Feature\Console;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(array $options = []): int
    {
        return $this->artisan('app:create-admin', [
            '--name' => 'Majedul Islam',
            '--email' => 'admin@example.com',
            '--password' => 'correct-horse-battery-staple',
            ...$options,
        ])->run();
    }

    public function test_it_creates_a_user_with_a_company_they_own()
    {
        $this->assertSame(0, $this->createAdmin(['--company' => 'Ocean Consumer Products']));

        $user = User::firstOrFail();

        $this->assertSame('Majedul Islam', $user->name);
        $this->assertSame('admin@example.com', $user->email);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));

        $team = $user->currentTeam;

        $this->assertNotNull($team);
        $this->assertSame('Ocean Consumer Products', $team->name);
        $this->assertTrue($user->ownsTeam($team));
    }

    /**
     * An unverified first user cannot reach the dashboard, and there is nobody
     * to send them a verification link.
     */
    public function test_the_first_user_is_verified_on_creation()
    {
        $this->createAdmin();

        $this->assertNotNull(User::firstOrFail()->email_verified_at);
    }

    public function test_the_company_name_defaults_to_the_persons_name()
    {
        $this->createAdmin();

        $this->assertSame("Majedul Islam's Company", Team::firstOrFail()->name);
    }

    public function test_they_can_actually_sign_in_afterwards()
    {
        $this->createAdmin();

        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'correct-horse-battery-staple',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_a_duplicate_email_is_refused_without_creating_anything()
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->assertSame(1, $this->createAdmin());
        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_a_weak_password_is_refused()
    {
        $this->assertSame(1, $this->createAdmin(['--password' => 'short']));
        $this->assertSame(0, User::count());
    }

    public function test_an_invalid_email_is_refused()
    {
        $this->assertSame(1, $this->createAdmin(['--email' => 'not-an-email']));
        $this->assertSame(0, User::count());
    }

    /**
     * The command exists precisely because factories are unavailable in
     * production, so it must not reach for one itself.
     */
    public function test_it_does_not_depend_on_factories_or_faker()
    {
        $source = file_get_contents(app_path('Console/Commands/CreateAdminUser.php'));

        $this->assertStringNotContainsString('factory(', $source);
        $this->assertStringNotContainsString('fake(', $source);
    }
}
