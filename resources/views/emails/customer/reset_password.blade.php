<x-mail::message>
# Hello {{ $customer->first_name }},

You are receiving this email because we received a password reset request for your account.

<x-mail::button :url="$url">
Reset Password
</x-mail::button>

This password reset link will expire in 60 minutes.

If you did not request a password reset, no further action is required.

Thanks,<br>
**Team Airventory**

---

If you’re having trouble clicking the button, copy and paste the URL below into your browser:

{{ $url }}

</x-mail::message>