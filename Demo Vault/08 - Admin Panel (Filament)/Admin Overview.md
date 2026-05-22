# Admin Overview (Filament)

> The full admin panel at `/admin` — how it's configured, what resources and widgets it contains, and how it integrates with Y's User model for access control.

---

## Concept Explained

Filament v4 is a full-featured admin panel framework for Laravel. A `PanelProvider` class configures the panel — its URL path, branding, colours, middleware, and which resources and widgets to discover. Resources are classes that define tables, forms, and actions for a model. Widgets appear on the dashboard.

---

## How it's Used in Y

File: `app/Providers/Filament/AdminPanelProvider.php`

### Panel configuration

```php
$panel
    ->default()
    ->id('admin')
    ->path('admin')                    // available at /admin
    ->viteTheme('resources/css/filament/admin/theme.css')
    ->login()
    ->brandName('Y')
    ->brandLogo(asset('images/Y-dark-remove.png'))
    ->darkMode(isForced: true)         // always dark — no toggle
    ->colors(['primary' => Color::Indigo, 'gray' => Color::Zinc])
    ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
    ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
```

### Access control

`User::canAccessPanel()` is the Filament gate:

```php
// app/Models/User.php
public function canAccessPanel(Panel $panel): bool
{
    return $this->is_admin === true;
}
```

Any `User` with `is_admin = true` can access `/admin`. Non-admins get a 403. `is_admin` is not in `#[Fillable]` — it must be set via `forceFill()`.

### Resources

**`UserResource`** — manages the `users` table:
- Table: avatar, name, username, email, profanity strikes, admin badge, ban status
- Filters: banned users, has strikes, admin status, joined period
- Actions (per row): Ban, Unban, Reset Strikes, Toggle Admin, Edit
- Separate pages: `ListUsers`, `ListBannedUsers` (filtered to `banned_at IS NOT NULL`), `EditUser`
- Navigation badge shows total user count

**`PostResource`** — read-only management of posts:
- Table: author avatar, username, body excerpt, like count, has-image icon, created date
- Filters: has image, has replies, has likes, popular (10+ likes), posted period
- Row action: Delete
- Navigation badge shows total post count (excluding reposts)
- Excludes reposts with `->whereNull('repost_of_id')` in the query

### Widgets (dashboard)

**`StatsOverviewWidget`** — four stat cards with sparkline charts:
- Total Users (with 14-day growth chart)
- Total Posts (with 14-day post chart)
- Banned Users (colour-coded: green = none, red = some)
- New Users Today (with 24-hour hourly chart using Carbon `whereBetween`)

**`TopPostsWidget`** — table of the 10 most-liked posts with author, excerpt, like count, reply count.

**`UserSignupsChart`** — line chart of user signups over time.

### Sidebar badge for Banned Users

```php
// AdminPanelProvider — manual navigation item
NavigationItem::make('Banned Users')
    ->icon('heroicon-o-no-symbol')
    ->badge(fn () => User::whereNotNull('banned_at')->count() ?: null)
    ->url('/admin/users/banned')
```

The badge shows the live banned count. `?: null` means "show nothing (not 0) when there are no banned users."

### Sidebar refresh after actions

All ban/unban/toggle_admin actions dispatch a Livewire browser event after execution:

```php
Action::make('ban')->action(function (User $record, Component $livewire) {
    $record->forceFill(['banned_at' => now()])->save();
    $livewire->dispatch('refresh-sidebar');
});
```

This triggers Filament's sidebar to re-render with updated badge counts.

---

## Key Code Snippet

```php
// UserResource — ban action with sidebar refresh
Action::make('ban')
    ->action(function (User $record, Component $livewire) {
        $record->forceFill(['banned_at' => now()])->save();
        Notification::make()->title('User banned')->danger()->send();
        $livewire->dispatch('refresh-sidebar');
    })
    ->visible(fn (User $record) => $record->banned_at === null)
    ->requiresConfirmation()
    ->color('danger')
    ->icon('heroicon-o-no-symbol'),
```

---

## Why This Approach

Filament's discovery system (`discoverResources`, `discoverWidgets`) auto-discovers any class in the configured namespace — no manual registration. This means adding a new resource is just creating the file. Forcing dark mode (`isForced: true`) in the admin panel is a deliberate branding decision — Y's admin has a distinct look from the public-facing UI. The `HasAvatarFallback` trait on both resources prevents duplicated avatar URL generation logic.

---

## Related Notes

- [[Users]]
- [[PHP 8 Attributes (Fillable, Hidden)]]
- [[Profanity Strike + Ban System]]
- [[Posts (replies + reposts)]]
