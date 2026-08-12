<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Support\CompanyUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform panel: creating companies, suspending them, and seeing what
 * they use.
 *
 * Companies deliberately cannot create companies — this is sold to them, so
 * one account is one customer. `TeamController::store` refuses anyone but a
 * platform admin for the same reason.
 */
class PlatformController extends Controller
{
    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->is_super_admin) {
            return redirect()->route('platform.index');
        }

        return Inertia::render('platform/login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        /*
         * One message for every failure — wrong address, wrong password, or a
         * real company user who found this page. Saying which would confirm
         * that an address exists, or that it is the platform admin's.
         */
        if (! $user || ! $user->is_super_admin || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('platform.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }

    /**
     * Every company on the platform, with what it is using.
     */
    public function index(): Response
    {
        $teams = Team::query()
            ->withTrashed()
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => CompanyUsage::for($team))
            ->all();

        return Inertia::render('platform/index', [
            'companies' => $teams,
            'totals' => [
                'companies' => count($teams),
                'suspended' => count(array_filter($teams, fn (array $c) => $c['isSuspended'])),
                'storageBytes' => array_sum(array_column($teams, 'storageBytes')),
                'users' => User::where('is_super_admin', false)->count(),
            ],
        ]);
    }

    /**
     * Create a company and the account that owns it.
     *
     * Both together, because a company nobody can sign into is not a sale.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', Password::default()],
        ]);

        DB::transaction(function () use ($data): void {
            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($data['owner_password']),
            ]);

            // Verified on creation: there is nobody to send a link to, and the
            // dashboard sits behind the `verified` middleware.
            $owner->forceFill(['email_verified_at' => now()])->save();

            app(CreateTeam::class)->handle($owner, $data['company'], isPersonal: true);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':company created.', ['company' => $data['company']]),
        ]);

        return to_route('platform.index');
    }

    /**
     * Stop a company using the system, or let it back in.
     *
     * Nothing is deleted either way — their books wait for them.
     */
    public function suspend(Request $request, Team $team): RedirectResponse
    {
        $suspend = $request->boolean('suspend');

        $team->update(['suspended_at' => $suspend ? now() : null]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $suspend
                ? __(':company suspended.', ['company' => $team->name])
                : __(':company reinstated.', ['company' => $team->name]),
        ]);

        return to_route('platform.index');
    }
}
