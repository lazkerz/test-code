<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return UserSummaryResource::collection(
            User::orderBy('id')->paginate($validated['per_page'] ?? 15)
        );
    }

    public function show(Request $request, User $user): UserResource
    {
        return $request->user()->is($user)
            ? new UserResource($user)
            : new UserSummaryResource($user);
    }
}
