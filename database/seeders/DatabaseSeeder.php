<?php

namespace Database\Seeders;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $testUser = User::factory()->create([
            'name' => 'Ben Shaw',
            'username' => 'benshaw',
            'email' => 'benshaw@gmail.com',
            'password' => 'P@ssword1',
            'bio' => 'Building Y. Lover of clean code and dark UIs.',
            'is_admin' => true,
        ]);

        $users = User::factory(49)->create()->prepend($testUser);

        // Follows — each user follows 10-20 random others (not everyone)
        $users->each(function (User $user) use ($users) {
            $others = $users->where('id', '!=', $user->id);
            $followCount = rand(10, 20);

            $others->random($followCount)->each(
                fn (User $other) => $user->follows()->create(['following_id' => $other->id])
            );
        });

        // Posts — each user makes 8-20 posts
        $users->each(function (User $user) {
            Post::factory(rand(8, 20))->create(['user_id' => $user->id]);
        });

        $allPosts = Post::all();

        // Likes — each post liked by 5-40% of users (realistic distribution)
        $allPosts->each(function (Post $post) use ($users) {
            $likerCount = (int) ($users->count() * (rand(5, 40) / 100));

            $likes = $users->random($likerCount)->map(fn (User $user) => [
                'user_id' => $user->id,
                'post_id' => $post->id,
            ])->toArray();

            Like::insertOrIgnore($likes);
        });

        // Replies — each post gets 0-4 replies from random users
        $allPosts->each(function (Post $post) use ($users) {
            $replyCount = rand(0, 4);

            if ($replyCount === 0) {
                return;
            }

            $users->random($replyCount)->each(function (User $user) use ($post) {
                Post::factory()->create([
                    'user_id' => $user->id,
                    'parent_post_id' => $post->id,
                ]);
            });
        });

        // Reposts — each user reposts 1-4 random posts from others
        $users->each(function (User $user) use ($allPosts) {
            $repostable = $allPosts->where('user_id', '!=', $user->id);
            $count = rand(1, 4);

            $repostable->random($count)->each(function (Post $post) use ($user) {
                Post::firstOrCreate(['user_id' => $user->id, 'repost_of_id' => $post->id]);
            });
        });
    }
}
