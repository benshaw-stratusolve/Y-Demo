@component('mail::message')
  # Welcome to Y, {{ $user->name }}!

  Thanks for signing up. Your account is ready to go.

  **Your username:** {{ $user->username }}

  Jump in and start posting.

  @component('mail::button', ['url' => config('app.url')])
  Go to Y
  @endcomponent

  Thanks,
  The Y Team
  @endcomponent