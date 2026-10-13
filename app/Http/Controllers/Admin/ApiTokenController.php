<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

#[Middleware('auth')]
#[Middleware('permission:api-access')]
class ApiTokenController extends Controller
{
    public function index(): View
    {
        $tokens = ApiToken::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('backend.api-tokens.index', compact('tokens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'expires' => 'nullable|integer|min:1',
            'scope' => 'nullable|in:full,read-write,read-only',
        ]);

        $expiresInMinutes = $request->input('expires')
            ? (int) $request->input('expires') * 1440 // convert days to minutes
            : null;

        $abilities = match ($request->input('scope', 'full')) {
            'read-only' => ['read'],
            'read-write' => ['read', 'write'],
            default => ['*'],
        };

        $result = ApiToken::createToken(
            auth()->user(),
            $request->input('name'),
            $abilities,
            $expiresInMinutes
        );

        return redirect()->route('backend.api-tokens.index')
            ->with('success', __('API token created successfully.'))
            ->with('plain_text_token', $result['plain_text']);
    }

    public function destroy(ApiToken $apiToken): RedirectResponse
    {
        if ($apiToken->user_id !== auth()->id()) {
            abort(403);
        }

        $apiToken->delete();

        return redirect()->route('backend.api-tokens.index')
            ->with('success', __('API token revoked.'));
    }
}
