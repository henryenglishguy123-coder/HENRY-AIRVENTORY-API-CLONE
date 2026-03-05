<x-mail::message>
    <x-slot:header>
        <x-mail::header :url="config('app.factory_panel_url')">
            Airventory Factory
        </x-mail::header>
    </x-slot:header>

    # Hey {{ $name }} 👋

    Welcome to **Airventory Factory**!
    We're excited to have you onboard via Google Login.

    Your account has been created successfully using Google Login.
    To finish setting up your account, please create a password so you can log in either with Google or directly using
    your email and password.

    <x-mail::panel>
        This secure link lets you set a password for your new account.
        It is valid for 24 hours and can only be used once.
    </x-mail::panel>

    <x-mail::button :url="$url">
        Set Your Password
    </x-mail::button>

    If you're having trouble clicking the button, copy and paste this URL into your browser:

    {{ $url }}

    Thanks,<br>
    **Team Airventory**

</x-mail::message>
