<x-mail::message>
# You've been invited!

You have been invited to join the **{{ $churchName }}** workspace on our production tools platform.

Please click the button below to register your account and join the team.

<x-mail::button :url="$invitationUrl">
Accept Invitation & Register
</x-mail::button>

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
