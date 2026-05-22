# Events + Listeners

> Laravel's event system — how Y fires an event on user registration and listens to send a welcome email.

---

## Concept Explained

Laravel's event/listener system is an implementation of the Observer pattern. An **event** is a class that carries data about something that happened. A **listener** is a class that responds to an event. Events and listeners are decoupled — the code that fires the event doesn't know or care what listens to it.

---

## How it's Used in Y

### The event: `Illuminate\Auth\Events\Registered`

Y doesn't define a custom event — it uses Laravel's built-in `Registered` event, which Fortify fires automatically after `CreateNewUser::create()` returns the new `User`. The event carries the user as `$event->user`.

### The listener: `SendWelcomeEmail`

```php
// app/Listeners/SendWelcomeEmail.php
class SendWelcomeEmail
{
    public function handle(Registered $event): void
    {
        Mail::to($event->user)->queue(new WelcomeEmail($event->user));
    }
}
```

`Mail::queue()` (not `Mail::send()`) pushes the email onto the queue — registration is fast even if the mail server is slow.

### Registration in AppServiceProvider

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Event::listen(Registered::class, SendWelcomeEmail::class);
    Model::preventLazyLoading(! app()->isProduction());
    $this->configureDefaults();
}
```

`Event::listen()` wires the event class to the listener class. Laravel resolves the listener from the container so dependency injection works in the listener's constructor.

---

## Key Code Snippet

```php
// The full flow on registration:
// 1. Fortify calls CreateNewUser::create()
// 2. CreateNewUser creates the user
// 3. Fortify fires: event(new Registered($user))
// 4. AppServiceProvider listener catches it:
Event::listen(Registered::class, SendWelcomeEmail::class);
// 5. SendWelcomeEmail::handle() queues the WelcomeEmail mailable
Mail::to($event->user)->queue(new WelcomeEmail($event->user));
```

---

## Why This Approach

Using the built-in `Registered` event rather than a custom one means the listener automatically fires for any registration path — including future OAuth or SSO flows that also fire `Registered`. The queue (`Mail::queue()` vs `Mail::send()`) ensures registration never blocks on SMTP timeouts. Events keep `CreateNewUser` focused on creating the user — it doesn't need to know anything about emails.

---

## Related Notes

- [[Registration Flow]]
- [[Laravel Notifications]]
- [[Jobs + Queues (ProcessPostImage)]]
