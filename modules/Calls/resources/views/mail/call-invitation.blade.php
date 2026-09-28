@component('mail::message')
# {{ $invitation->subject }}

{!! $body !!}

@component('mail::panel')
**Recording and transcription notice**

{!! $notice !!}
@endcomponent

@component('mail::button', ['url' => config('app.url')])
Open DeAlly
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
