<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return UserResource::collection(
            User::orderBy('id')->paginate($validated['per_page'] ?? 15)
        );
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }
}
