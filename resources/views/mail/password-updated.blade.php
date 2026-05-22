@component('mail::message')
# Your password was updated, {{ $user->name }}

Your Y password was changed successfully.

If you made this change, no further action is needed.

If this was not you, reset your password immediately and review your account security settings.

@component('mail::button', ['url' => $url])
Go to Y
@endcomponent

Thanks,
The Y Team
@endcomponent
