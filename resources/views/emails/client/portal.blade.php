@component('mail::message')
# Hello {{ $user->first_name }}

Great news! You can now log in to your client portal to access your purchased property details.

---

### 🔑 Your Login Credentials:

**Email:** {{ $user->email }}  
**Default Password:** {{ $password }}

---

⚠️ For security, please change your password immediately after your first login.

---

🎬 New to the platform? Watch our quick explainer video to learn how to navigate your portal:

@component('mail::button', ['url' => 'https://www.loom.com/share/799c102e23c7477f9612d68b52652b11'])
📺 Watch Explainer Video
@endcomponent

@component('mail::button', ['url' => 'https://portal.gtextland.com/sign-in'])
🚀 Login to Your Portal
@endcomponent

---

If you have any questions, contact us at 
cfu@gtexthomes.com  or 
+234 703 193 0951  

We’re here to help!

---

Thanks,<br>
**The GTEXT Land Team**
@endcomponent