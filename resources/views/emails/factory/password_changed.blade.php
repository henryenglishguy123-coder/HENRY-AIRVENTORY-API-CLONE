<x-mail::message>
# Password Successfully Updated

Hey {{ $name }},

Just letting you know — your factory account password was changed.

**When:** {{ $changedAt->toDayDateTimeString() }}  
@if($ip)
**IP Address:** {{ $ip }}
@endif

If this was you, you're all good.  
If you didn't make this change, please reset your password immediately: {{ $resetUrl }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
