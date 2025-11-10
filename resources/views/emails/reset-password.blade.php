<x-mail::message>
    # Hello {{ $recipientName }},

    Your password has been successfully reset.

    <x-mail::panel>
        **New Password:** {{ $newPassword }}
    </x-mail::panel>

    You can now log in using your new credentials.

    If you did not request this change, please contact support immediately.

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>