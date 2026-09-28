@component('mail::message')
# {{ $invitation->subject }}

{!! $body !!}

@component('mail::button', ['url' => config('app.url')])
Open DeAlly
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
