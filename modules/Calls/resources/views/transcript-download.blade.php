{{ strtoupper($call->company) }} — CALL TRANSCRIPT
{{ $call->name }} · {{ $call->date->format('F j, Y g:ia') }} · {{ $call->duration ?: '0m' }}
============================================
@forelse ($lines as $line)
[{{ $line->is_agent ? 'AGENT' : strtoupper($line->speaker) }}]: {{ $line->text }}
@if ($line->linked_text)   » {{ $line->linked_type === 'competitor' ? 'Battle card shown' : $line->linked_type }} · {{ $line->linked_text }}
@endif
@empty
No transcript saved for this call.
@endforelse