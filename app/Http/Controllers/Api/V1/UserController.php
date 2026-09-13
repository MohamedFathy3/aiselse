<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Admin-only. Route-gated by `role:admin` middleware (see routes/api.php)
 * AND re-checked here - never trust the route gate alone (section 3).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $users = User::query()
            ->when($request->string('role')->isNotEmpty(), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return UserResource::collection($users);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');

        $user->update(['is_active' => false]);

        return response()->json(['message' => 'User deactivated.']);
    }
}
