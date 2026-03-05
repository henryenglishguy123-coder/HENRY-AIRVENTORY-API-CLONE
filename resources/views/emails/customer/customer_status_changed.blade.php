<x-mail::message>
# {{ __('Account Status Update') }}

{{ __('Hello') }} **{{ $customer->first_name }}**,  
{{ __('We wanted to let you know that your customer account on') }} **{{ config('app.name') }}** **{{ __('has been updated.') }}**

@switch($action)
    @case('enable')
        > {{ __('Good news! Your account has been successfully reactivated. You can now log in and continue using our services.') }}
        @break

    @case('disable')
        > {{ __('Your account has been disabled. If you believe this was a mistake, please contact our support team.') }}
        @break

    @case('blocked')
        > {{ __('Your account has been blocked due to a violation of our policies. Please reach out to support for more details.') }}
        @break

    @case('suspended')
        > {{ __('Your account has been suspended. For further clarification or to resolve this, please contact support.') }}
        @break

    @case('deleted')
        > {{ __('Your account has been deleted. If this was not intentional, please contact us immediately.') }}
        @break

    @default
        > {{ __('Your account status has been updated. If you need more information, feel free to contact us.') }}
@endswitch

{{ __('If you have any questions or need further assistance, our support team is always here to help.') }}

<x-mail::button :url="$loginUrl">
    {{ __('Access Your Account') }}
</x-mail::button>

---
{{ __('Warm regards,') }}  
**{{ config('app.name') }} {{ __('Support Team') }}**

{{ __('Need help?') }}  
[{{ __('Contact Support') }}](mailto:{{ config('mail.from.address', 'support@yourdomain.com') }})
</x-mail::message>