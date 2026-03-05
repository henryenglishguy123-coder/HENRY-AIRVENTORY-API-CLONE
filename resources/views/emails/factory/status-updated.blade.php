<x-mail::message>
# Your Factory Status Update

Dear {{ $factory->first_name ?? __('Factory Owner') }},

We are writing to inform you that there has been an update regarding your factory account status on our platform.

@if(!empty($changes))
<x-mail::panel>
### Following changes were made:

@foreach($changes as $type => $change)
**{{ ucwords(str_replace('_', ' ', $type)) }}:**
Changed from *{{ $change['old'] ?? 'N/A' }}* to **{{ $change['new'] ?? 'N/A' }}**

@endforeach
</x-mail::panel>
@endif

@if(!empty($reason))
<x-mail::panel>
### Reason provided by the Administrator:

{{ $reason }}
</x-mail::panel>
@endif

If you have any questions or require further assistance, please do not hesitate to contact our support team.

Best regards,<br>
**The Airventory Team**

<x-mail::subcopy>
This is an automated message, please do not reply directly to this email.
</x-mail::subcopy>
</x-mail::message>
