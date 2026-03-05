<x-mail::message>
# Hey {{ $name }} 👋

Welcome to **Airventory Factory**!  
We're excited to have you onboard.

Please use the following verification code to verify your email address:

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

**This code will expire in 15 minutes.**

If you didn't create this account, no worries — you can safely ignore this email.

Thanks,<br>
**Team Airventory**

</x-mail::message>
