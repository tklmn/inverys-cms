<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\EmailChange;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'passwordRules' => PasswordPolicy::rules(),
            'passwordPolicy' => PasswordPolicy::policy(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:'.User::class],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // No "unique" here: that error would tell strangers which addresses
            // have an account, which "forgot password" deliberately hides
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], ['name' => __('translation.name')]);

        // A taken address, or one held for another account's "This wasn't me"
        // link (App\Support\EmailChange), gets the same answer as a new
        // registration; nothing is created. New accounts wait for activation
        // anyway, so the answer promises nothing. The hash keeps the timing alike.
        $email = (string) $request->email;
        if (User::withTrashed()->whereRaw('LOWER(email) = ?', [strtolower($email)])->exists() || EmailChange::isReserved($email)) {
            Hash::make((string) $request->password);

            return redirect(route('login'))->with('status', __('auth.registration_pending'));
        }

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'is_active' => false,
            ]);

            $defaultRole = Role::named('user');
            if ($defaultRole) {
                $user->roles()->attach($defaultRole->id);
            }

            return $user;
        });

        event(new Registered($user));

        return redirect(route('login'))->with('status', __('auth.registration_pending'));
    }
}
