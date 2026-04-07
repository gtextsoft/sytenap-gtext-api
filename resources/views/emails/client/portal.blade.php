@component('mail::message')
# Hello {{ $user->first_name }}

Your portal is ready.

**Email:** {{ $user->email }}  
**Password:** {{ $password }}

@component('mail::button', ['url' => 'https://portal.gtextland.com/sign-in'])
🚀 Login to Your Portal
@endcomponent

@component('mail::button', ['url' => 'https://www.loom.com/share/799c102e23c7477f9612d68b52652b11'])
📺 Watch Explainer Video
@endcomponent

Thanks,<br>
GTEXT Land
@endcomponent