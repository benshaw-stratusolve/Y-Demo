# Twitter Clone Data Model Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete data model for Y — a Twitter clone supporting posts, replies, retweets, likes, follows, hashtags, media attachments, and direct messages.

**Architecture:** 10 new migrations add tables for user profile fields, the social graph (posts, likes, follows), content (hashtags, media), and DMs (conversations, messages). Each table gets a corresponding Eloquent model with typed relationships and a factory for testing. Seeders populate realistic demo data.

**Tech Stack:** Laravel 13, Eloquent ORM, Pest v4, MySQL, PHP 8.4

---

## File Structure

**New migrations:**
- `database/migrations/..._add_profile_fields_to_users_table.php` — username, bio, location, website, banner
- `database/migrations/..._create_posts_table.php`
- `database/migrations/..._create_likes_table.php`
- `database/migrations/..._create_follows_table.php`
- `database/migrations/..._create_hashtags_table.php`
- `database/migrations/..._create_hashtag_post_table.php`
- `database/migrations/..._create_media_table.php`
- `database/migrations/..._create_conversations_table.php`
- `database/migrations/..._create_conversation_user_table.php`
- `database/migrations/..._create_messages_table.php`

**New models:** `Post`, `Like`, `Follow`, `Hashtag`, `Media`, `Conversation`, `Message`

**Modified:** `app/Models/User.php` — profile fields + relationships

**New factories:** `PostFactory`, `LikeFactory`, `FollowFactory`, `HashtagFactory`, `ConversationFactory`, `MessageFactory`

**Modified:** `database/factories/UserFactory.php` — add username, bio, location, website, banner

**New seeders:** `UserSeeder`, `PostSeeder`

**Modified:** `database/seeders/DatabaseSeeder.php`

**Tests:** `tests/Feature/DataModelTest.php`

---

### Task 1: Users profile fields

**Files:**
- Create: `database/migrations/..._add_profile_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/DataModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DataModelTest.php`:

```php
<?php

use App\Models\User;

it('stores user profile fields', function () {
    $user = User::factory()->create([
        'username' => 'benshaw',
        'bio' => 'Building cool stuff.',
        'location' => 'London',
        'website' => 'https://benshaw.dev',
        'banner' => null,
    ]);

    expect($user->refresh())
        ->username->toBe('benshaw')
        ->bio->toBe('Building cool stuff.')
        ->location->toBe('London')
        ->website->toBe('https://benshaw.dev')
        ->banner->toBeNull();
});

it('enforces unique usernames', function () {
    User::factory()->create(['username' => 'duplicate']);

    expect(fn () => User::factory()->create(['username' => 'duplicate']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="stores user profile fields"
```

Expected: FAIL — column `username` does not exist.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_profile_fields_to_users_table --no-interaction
```

Replace the generated file content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('name');
            $table->text('bio')->nullable()->after('avatar');
            $table->string('location')->nullable()->after('bio');
            $table->string('website')->nullable()->after('location');
            $table->string('banner')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'bio', 'location', 'website', 'banner']);
        });
    }
};
```

- [ ] **Step 4: Update the User model**

In `app/Models/User.php`, update the `#[Fillable]` attribute:

```php
#[Fillable(['name', 'email', 'password', 'avatar', 'username', 'bio', 'location', 'website', 'banner'])]
```

- [ ] **Step 5: Update UserFactory**

In `database/factories/UserFactory.php`, add to the `definition()` return array:

```php
'username' => fake()->unique()->userName(),
'bio' => fake()->optional()->sentence(),
'location' => fake()->optional()->city(),
'website' => fake()->optional()->url(),
'banner' => null,
```

- [ ] **Step 6: Run the migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
php artisan test --compact --filter="stores user profile fields|enforces unique usernames"
```

Expected: 2 passed.

- [ ] **Step 8: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```bash
git add database/migrations/ app/Models/User.php database/factories/UserFactory.php tests/Feature/DataModelTest.php
git commit -m "feat: add profile fields to users table"
```

---

### Task 2: Posts table

**Files:**
- Create: `database/migrations/..._create_posts_table.php`
- Create: `app/Models/Post.php`
- Create: `database/factories/PostFactory.php`
- Modify: `app/Models/User.php`
- Modify: `tests/Feature/DataModelTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/DataModelTest.php`:

```php
use App\Models\Post;

it('creates a top-level post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create(['body' => 'Hello world']);

    expect($post->refresh())
        ->user_id->toBe($user->id)
        ->body->toBe('Hello world')
        ->parent_post_id->toBeNull()
        ->repost_of_id->toBeNull();
});

it('creates a reply by setting parent_post_id', function () {
    $parent = Post::factory()->create();
    $reply = Post::factory()->create(['parent_post_id' => $parent->id]);

    expect($reply->parent->id)->toBe($parent->id);
    expect($parent->replies)->toHaveCount(1);
});

it('creates a retweet by setting repost_of_id', function () {
    $original = Post::factory()->create();
    $retweet = Post::factory()->create(['repost_of_id' => $original->id, 'body' => null]);

    expect($retweet->repostOf->id)->toBe($original->id);
    expect($original->reposts)->toHaveCount(1);
});

it('user has many posts', function () {
    $user = User::factory()->create();
    Post::factory()->count(3)->for($user)->create();

    expect($user->posts)->toHaveCount(3);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="creates a top-level post"
```

Expected: FAIL — class Post not found.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_posts_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->foreignId('parent_post_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $table->foreignId('repost_of_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $table->timestamps();

            $table->index('parent_post_id');
            $table->index('repost_of_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

- [ ] **Step 4: Create the Post model**

```bash
php artisan make:model Post --no-interaction
```

Replace the generated content of `app/Models/Post.php`:

```php
<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'body', 'parent_post_id', 'repost_of_id'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'parent_post_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Post::class, 'parent_post_id');
    }

    public function repostOf(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'repost_of_id');
    }

    public function reposts(): HasMany
    {
        return $this->hasMany(Post::class, 'repost_of_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('sort_order');
    }
}
```

- [ ] **Step 5: Create PostFactory**

```bash
php artisan make:factory PostFactory --model=Post --no-interaction
```

Replace content of `database/factories/PostFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'parent_post_id' => null,
            'repost_of_id' => null,
        ];
    }
}
```

- [ ] **Step 6: Add User→posts relationship**

In `app/Models/User.php`, add the import and methods:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Add to the User class:

```php
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

public function likes(): HasMany
{
    return $this->hasMany(Like::class);
}

public function follows(): HasMany
{
    return $this->hasMany(Follow::class, 'follower_id');
}

public function followers(): HasMany
{
    return $this->hasMany(Follow::class, 'following_id');
}

public function following(): HasMany
{
    return $this->hasMany(Follow::class, 'follower_id');
}
```

Also add this import for conversations (used in Task 7):

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

And this method:

```php
public function conversations(): BelongsToMany
{
    return $this->belongsToMany(Conversation::class)->withPivot('joined_at');
}
```

- [ ] **Step 7: Run the migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 8: Run tests to verify they pass**

```bash
php artisan test --compact --filter="creates a top-level post|creates a reply|creates a retweet|user has many posts"
```

Expected: 4 passed.

- [ ] **Step 9: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 10: Commit**

```bash
git add database/migrations/ app/Models/ database/factories/PostFactory.php tests/Feature/DataModelTest.php
git commit -m "feat: add posts table with replies and retweet support"
```

---

### Task 3: Likes table

**Files:**
- Create: `database/migrations/..._create_likes_table.php`
- Create: `app/Models/Like.php`
- Create: `database/factories/LikeFactory.php`
- Modify: `tests/Feature/DataModelTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/DataModelTest.php`:

```php
use App\Models\Like;

it('user can like a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    Like::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);

    expect($post->likes)->toHaveCount(1);
    expect($user->likes)->toHaveCount(1);
});

it('enforces one like per user per post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    Like::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);

    expect(fn () => Like::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="user can like a post"
```

Expected: FAIL — class Like not found.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_likes_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
```

- [ ] **Step 4: Create the Like model**

```bash
php artisan make:model Like --no-interaction
```

Replace content of `app/Models/Like.php`:

```php
<?php

namespace App\Models;

use Database\Factories\LikeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'post_id'])]
class Like extends Model
{
    /** @use HasFactory<LikeFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

- [ ] **Step 5: Create LikeFactory**

```bash
php artisan make:factory LikeFactory --model=Like --no-interaction
```

Replace content of `database/factories/LikeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Like>
 */
class LikeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
        ];
    }
}
```

- [ ] **Step 6: Run the migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 7: Run tests**

```bash
php artisan test --compact --filter="user can like a post|enforces one like per user per post"
```

Expected: 2 passed.

- [ ] **Step 8: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```bash
git add database/migrations/ app/Models/Like.php database/factories/LikeFactory.php tests/Feature/DataModelTest.php
git commit -m "feat: add likes table"
```

---

### Task 4: Follows table

**Files:**
- Create: `database/migrations/..._create_follows_table.php`
- Create: `app/Models/Follow.php`
- Create: `database/factories/FollowFactory.php`
- Modify: `tests/Feature/DataModelTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/DataModelTest.php`:

```php
use App\Models\Follow;

it('user can follow another user', function () {
    $follower = User::factory()->create();
    $following = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'following_id' => $following->id]);

    expect($follower->following)->toHaveCount(1);
    expect($following->followers)->toHaveCount(1);
});

it('enforces unique follows', function () {
    $follower = User::factory()->create();
    $following = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'following_id' => $following->id]);

    expect(fn () => Follow::factory()->create(['follower_id' => $follower->id, 'following_id' => $following->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="user can follow another user"
```

Expected: FAIL — class Follow not found.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_follows_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
```

- [ ] **Step 4: Create the Follow model**

```bash
php artisan make:model Follow --no-interaction
```

Replace content of `app/Models/Follow.php`:

```php
<?php

namespace App\Models;

use Database\Factories\FollowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['follower_id', 'following_id'])]
class Follow extends Model
{
    /** @use HasFactory<FollowFactory> */
    use HasFactory;

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
```

- [ ] **Step 5: Create FollowFactory**

```bash
php artisan make:factory FollowFactory --model=Follow --no-interaction
```

Replace content of `database/factories/FollowFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Follow>
 */
class FollowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'follower_id' => User::factory(),
            'following_id' => User::factory(),
        ];
    }
}
```

- [ ] **Step 6: Run the migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 7: Run tests**

```bash
php artisan test --compact --filter="user can follow another user|enforces unique follows"
```

Expected: 2 passed.

- [ ] **Step 8: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```bash
git add database/migrations/ app/Models/Follow.php database/factories/FollowFactory.php tests/Feature/DataModelTest.php
git commit -m "feat: add follows table"
```

---

### Task 5: Hashtags

**Files:**
- Create: `database/migrations/..._create_hashtags_table.php`
- Create: `database/migrations/..._create_hashtag_post_table.php`
- Create: `app/Models/Hashtag.php`
- Create: `database/factories/HashtagFactory.php`
- Modify: `tests/Feature/DataModelTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/DataModelTest.php`:

```php
use App\Models\Hashtag;

it('attaches hashtags to a post', function () {
    $post = Post::factory()->create();
    $hashtags = Hashtag::factory()->count(2)->create();
    $post->hashtags()->attach($hashtags);

    expect($post->hashtags)->toHaveCount(2);
});

it('finds posts by hashtag', function () {
    $hashtag = Hashtag::factory()->create(['name' => 'laravel']);
    $post = Post::factory()->create();
    $post->hashtags()->attach($hashtag);

    expect($hashtag->posts)->toHaveCount(1);
    expect($hashtag->posts->first()->id)->toBe($post->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="attaches hashtags to a post"
```

Expected: FAIL — class Hashtag not found.

- [ ] **Step 3: Create hashtags migration**

```bash
php artisan make:migration create_hashtags_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hashtags');
    }
};
```

- [ ] **Step 4: Create hashtag_post pivot migration**

```bash
php artisan make:migration create_hashtag_post_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hashtag_post', function (Blueprint $table) {
            $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['hashtag_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hashtag_post');
    }
};
```

- [ ] **Step 5: Create the Hashtag model**

```bash
php artisan make:model Hashtag --no-interaction
```

Replace content of `app/Models/Hashtag.php`:

```php
<?php

namespace App\Models;

use Database\Factories\HashtagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Hashtag extends Model
{
    /** @use HasFactory<HashtagFactory> */
    use HasFactory;

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
```

- [ ] **Step 6: Create HashtagFactory**

```bash
php artisan make:factory HashtagFactory --model=Hashtag --no-interaction
```

Replace content of `database/factories/HashtagFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Hashtag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hashtag>
 */
class HashtagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
```

- [ ] **Step 7: Run the migrations**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 8: Run tests**

```bash
php artisan test --compact --filter="attaches hashtags to a post|finds posts by hashtag"
```

Expected: 2 passed.

- [ ] **Step 9: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 10: Commit**

```bash
git add database/migrations/ app/Models/Hashtag.php database/factories/HashtagFactory.php tests/Feature/DataModelTest.php
git commit -m "feat: add hashtags and hashtag_post tables"
```

---

### Task 6: Media table

**Files:**
- Create: `database/migrations/..._create_media_table.php`
- Create: `app/Models/Media.php`
- Modify: `tests/Feature/DataModelTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/DataModelTest.php`:

```php
use App\Models\Media;

it('attaches media to a post', function () {
    $post = Post::factory()->create();
    Media::create(['post_id' => $post->id, 'path' => 'images/photo.jpg', 'type' => 'image', 'sort_order' => 0]);

    expect($post->media)->toHaveCount(1);
    expect($post->media->first()->type)->toBe('image');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="attaches media to a post"
```

Expected: FAIL — class Media not found.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration create_media_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->enum('type', ['image', 'video', 'gif']);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
```

- [ ] **Step 4: Create the Media model**

```bash
php artisan make:model Media --no-interaction
```

Replace content of `app/Models/Media.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['post_id', 'path', 'type', 'sort_order'])]
class Media extends Model
{
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

- [ ] **Step 5: Run the migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact --filter="attaches media to a post"
```

Expected: 1 passed.

- [ ] **Step 7: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/ app/Models/Media.php tests/Feature/DataModelTest.php
git commit -m "feat: add media table"
```

---

### Task 7: Direct Messages (conversations + messages)

**Files:**
- Create: `database/migrations/..._create_conversations_table.php`
- Create: `database/migrations/..._create_conversation_user_table.php`
- Create: `database/migrations/..._create_messages_table.php`
- Create: `app/Models/Conversation.php`
- Create: `app/Models/Message.php`
- Create: `database/factories/ConversationFactory.php`
- Create: `database/factories/MessageFactory.php`
- Modify: `tests/Feature/DataModelTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/DataModelTest.php`:

```php
use App\Models\Conversation;
use App\Models\Message;

it('creates a conversation with participants', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $conversation->participants()->attach([$userA->id, $userB->id]);

    expect($conversation->participants)->toHaveCount(2);
    expect($userA->conversations)->toHaveCount(1);
});

it('sends a message in a conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $conversation->participants()->attach($user->id);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => 'Hey there!',
    ]);

    expect($conversation->messages)->toHaveCount(1);
    expect($conversation->messages->first()->body)->toBe('Hey there!');
    expect($message->read_at)->toBeNull();
});

it('marks a message as read', function () {
    $message = Message::factory()->create(['read_at' => null]);
    $message->update(['read_at' => now()]);

    expect($message->fresh()->read_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="creates a conversation with participants"
```

Expected: FAIL — class Conversation not found.

- [ ] **Step 3: Create conversations migration**

```bash
php artisan make:migration create_conversations_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
```

- [ ] **Step 4: Create conversation_user pivot migration**

```bash
php artisan make:migration create_conversation_user_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_user');
    }
};
```

- [ ] **Step 5: Create messages migration**

```bash
php artisan make:migration create_messages_table --no-interaction
```

Replace content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

- [ ] **Step 6: Create the Conversation model**

```bash
php artisan make:model Conversation --no-interaction
```

Replace content of `app/Models/Conversation.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }
}
```

- [ ] **Step 7: Create the Message model**

```bash
php artisan make:model Message --no-interaction
```

Replace content of `app/Models/Message.php`:

```php
<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'user_id', 'body', 'read_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

- [ ] **Step 8: Create ConversationFactory**

```bash
php artisan make:factory ConversationFactory --model=Conversation --no-interaction
```

Replace content of `database/factories/ConversationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}
```

- [ ] **Step 9: Create MessageFactory**

```bash
php artisan make:factory MessageFactory --model=Message --no-interaction
```

Replace content of `database/factories/MessageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'read_at' => null,
        ];
    }
}
```

- [ ] **Step 10: Run the migrations**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 11: Run tests**

```bash
php artisan test --compact --filter="creates a conversation with participants|sends a message in a conversation|marks a message as read"
```

Expected: 3 passed.

- [ ] **Step 12: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 13: Commit**

```bash
git add database/migrations/ app/Models/ database/factories/ tests/Feature/DataModelTest.php
git commit -m "feat: add conversations and messages tables for DMs"
```

---

### Task 8: Seeders

**Files:**
- Create: `database/seeders/UserSeeder.php`
- Create: `database/seeders/PostSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create UserSeeder**

```bash
php artisan make:seeder UserSeeder --no-interaction
```

Replace content of `database/seeders/UserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory()->count(20)->create();

        $users->each(function (User $user) use ($users) {
            $users->where('id', '!=', $user->id)
                ->random(rand(3, 7))
                ->each(fn (User $target) => Follow::firstOrCreate([
                    'follower_id' => $user->id,
                    'following_id' => $target->id,
                ]));
        });
    }
}
```

- [ ] **Step 2: Create PostSeeder**

```bash
php artisan make:seeder PostSeeder --no-interaction
```

Replace content of `database/seeders/PostSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Hashtag;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $hashtags = Hashtag::factory()->count(15)->create();

        $users->each(function (User $user) use ($users, $hashtags) {
            $posts = Post::factory()->count(rand(3, 8))->for($user)->create();

            $posts->each(function (Post $post) use ($hashtags) {
                $post->hashtags()->attach(
                    $hashtags->random(rand(0, 2))->pluck('id')
                );
            });

            $randomPost = Post::inRandomOrder()->first();
            if ($randomPost) {
                Post::factory()->for($user)->create(['parent_post_id' => $randomPost->id]);
            }
        });

        $allPosts = Post::all();
        $users->each(function (User $user) use ($allPosts) {
            $allPosts->random(min(rand(5, 15), $allPosts->count()))
                ->each(fn (Post $post) => Like::firstOrCreate([
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                ]));
        });
    }
}
```

- [ ] **Step 3: Update DatabaseSeeder**

Replace content of `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PostSeeder::class,
        ]);
    }
}
```

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add database/seeders/
git commit -m "feat: add user and post seeders"
```

---

### Task 9: Fresh migration and full verification

- [ ] **Step 1: Run migrate:fresh with seeding**

```bash
php artisan migrate:fresh --seed --no-interaction
```

Expected: All 16 migrations run, seeders complete without errors.

- [ ] **Step 2: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass (including existing auth and FlockAI tests).

- [ ] **Step 3: Verify in TablePlus**

Open TablePlus and hit `Cmd+R`. Confirm these tables are visible with data:
`posts`, `likes`, `follows`, `hashtags`, `hashtag_post`, `media`, `conversations`, `conversation_user`, `messages`, and `users` (with username, bio, location, website, banner columns).

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: complete data model — migrate:fresh verified"
```
