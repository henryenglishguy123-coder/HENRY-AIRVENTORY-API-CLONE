<x-mail::message>
# Hey {{ $name }} 👋

Welcome to **Airventory**!  
We’re excited to have you onboard.

Before you start using your account, please verify your email address so we know it’s really you.

<x-mail::button :url="$verifyUrl">
Verify Email
</x-mail::button>

If you didn’t create this account, no worries — you can safely ignore this email.

Thanks,<br>
**Team Airventory**

---

If you’re having trouble clicking the button, copy and paste the URL below into your browser:

{{ $verifyUrl }}

</x-mail::message>
