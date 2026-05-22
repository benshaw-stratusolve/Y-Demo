# Registration Flow

> Everything that happens from the moment a user submits the registration form to them landing on the dashboard.

---

## Concept Explained

Fortify intercepts the `POST /register` request and delegates user creation to the `CreateNewUser` action class. This class validates input (using shared trait rules), creates the `User`, and triggers a welcome notification. Laravel's `Registered` event is fired automatically after creation, which a listener picks up to send a welcome email.

---

## How it's Used in Y

### Step 1 — Validation (`app/Actions/Fortify/CreateNewUser.php`)

Reuses two traits to keep rules DRY:
- `ProfileValidationRules::profileRules()` — validates name, username (unique, alphanumeric+underscore, max 30), email (unique), bio
- `PasswordValidationRules::passwordRules()` — requires min 8 chars, at least one number and one symbol, with confirmation

### Step 2 — User creation

```php
$user = User::create([
    'name'     => $input['name'],
    'username' => $input['username'],
    'email'    => $input['email'],
    'password' => $input['password'], // auto-hashed by cast
]);
```

### Step 3 — Welcome notification (in-app)

```php
$user->notify(new WelcomeNotification);
```

Stores a `welcome` type entry in the `notifications` table immediately.

### Step 4 — Welcome email (queued)

The `Registered` event fires. `AppServiceProvider::boot()` registers:

```php
Event::listen(Registered::class, SendWelcomeEmail::class);
```

`SendWelcomeEmail::handle()` queues a `WelcomeEmail` mailable so registration doesn't block on SMTP.

---

## Key Code Snippet

```php
// app/Actions/Fortify/CreateNewUser.php
public function create(array $input): User
{
    Validator::make($input, [
        ...$this->profileRules(),
        'password' => $this->passwordRules(),
    ])->validate();

    $user = User::create([
        'name' => $input['name'], 'username' => $input['username'],
        'email' => $input['email'], 'password' => $input['password'],
    ]);

    $user->notify(new WelcomeNotification);

    return $user;
}
```

---

## Why This Approach

Putting user creation in a dedicated action class (rather than a controller) keeps `CreateNewUser` independently testable and allows Fortify to swap it in at the framework level. The separation between the database notification (immediate) and the email (queued) means registration is fast regardless of mail server latency.

---

## Related Notes

- [[Fortify Setup]]
- [[Laravel Notifications]]
- [[Events + Listeners]]
- [[PHP 8 Attributes (Fillable, Hidden)]]
- [[Form Requests]]
