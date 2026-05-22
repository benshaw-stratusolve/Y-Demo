<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $query = trim($validated['q'] ?? '');

        if (mb_strlen($query) < 2) {
            $recentPosts = Post::with('user:id,name,username,avatar')
                ->whereNotNull('body')
                ->whereNull('repost_of_id')
                ->whereNull('parent_post_id')
                ->latest()
                ->limit(8)
                ->get(['id', 'body', 'user_id']);

            return response()->json([
                'query' => $query,
                'users' => [],
                'posts' => $recentPosts,
                'suggestions' => [],
            ]);
        }

        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $likeQuery = "%{$escapedQuery}%";

        $users = $this->matchingUsers($escapedQuery, $likeQuery);
        $posts = $this->matchingPosts($likeQuery);

        return response()->json([
            'query' => $query,
            'users' => $users,
            'posts' => $posts,
            'suggestions' => $this->suggestionsFor($query, $users),
        ]);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function matchingUsers(string $query, string $likeQuery): EloquentCollection
    {
        return User::query()
            ->where(function (Builder $builder) use ($likeQuery): void {
                $builder
                    ->where('name', 'like', $likeQuery)
                    ->orWhere('username', 'like', $likeQuery);
            })
            ->orderByRaw('CASE WHEN username LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', ["{$query}%", "{$query}%"])
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'username', 'avatar']);
    }

    /**
     * @return EloquentCollection<int, Post>
     */
    private function matchingPosts(string $likeQuery): EloquentCollection
    {
        return Post::with('user:id,name,username,avatar')
            ->whereNotNull('body')
            ->where('body', 'like', $likeQuery)
            ->whereNull('repost_of_id')
            ->latest()
            ->limit(5)
            ->get(['id', 'body', 'user_id']);
    }

    /**
     * @param  EloquentCollection<int, User>  $matchedUsers
     * @return array<int, string>
     */
    private function suggestionsFor(string $query, EloquentCollection $matchedUsers): array
    {
        $normalizedQuery = Str::of($query)->lower()->squish()->toString();

        $userCandidates = $matchedUsers
            ->flatMap(fn (User $user): array => array_filter([$user->name, $user->username]));

        $nearbyUserCandidates = User::query()
            ->where(function (Builder $builder) use ($query): void {
                $builder
                    ->where('name', 'like', mb_substr($query, 0, 1).'%')
                    ->orWhere('username', 'like', mb_substr($query, 0, 1).'%');
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['name', 'username'])
            ->flatMap(fn (User $user): array => array_filter([$user->name, $user->username]));

        return $userCandidates
            ->merge($nearbyUserCandidates)
            ->map(fn (string $candidate): string => Str::of($candidate)->squish()->trim('@')->toString())
            ->filter(fn (string $candidate): bool => mb_strlen($candidate) >= 2)
            ->unique(fn (string $candidate): string => Str::lower($candidate))
            ->reject(fn (string $candidate): bool => Str::lower($candidate) === $normalizedQuery)
            ->sortBy(fn (string $candidate): int => $this->suggestionDistance($normalizedQuery, Str::lower($candidate)))
            ->take(5)
            ->values()
            ->all();
    }

    private function suggestionDistance(string $query, string $candidate): int
    {
        if (Str::contains($candidate, $query)) {
            return 0;
        }

        if (Str::startsWith($candidate, mb_substr($query, 0, 1))) {
            return levenshtein($query, $candidate);
        }

        return levenshtein($query, $candidate) + 10;
    }
}
