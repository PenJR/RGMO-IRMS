<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile settings form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the authenticated user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Save the authenticated user's preferred top-level sidebar order.
     */
    public function updateSidebarOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'max:'.count(User::SIDEBAR_ITEMS)],
            'order.*' => ['required', 'string', 'distinct', Rule::in(User::SIDEBAR_ITEMS)],
        ]);

        $request->user()->update(['sidebar_order' => array_values($validated['order'])]);

        return response()->json(['message' => 'Sidebar order saved.']);
    }
}
