<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = Post::with('user')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post->load('user'));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $request->user()->posts()->create($request->validated());

        return (new PostResource($post->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        Gate::authorize('update', $post);

        $post->update($request->validated());

        return new PostResource($post->load('user'));
    }

    public function destroy(Post $post): Response
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }
}
