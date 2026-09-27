{{ strtoupper($call->company) }} — CALL TRANSCRIPT
{{ $call->name }} · {{ $call->date->format('F j, Y g:ia') }} · {{ $call->duration ?: '0m' }}
============================================
@forelse ($lines as $line)
[{{ $line->is_agent ? 'AGENT' : strtoupper($line->speaker) }}]: {{ $line->text }}
@if ($line->linked_text)   » {{ $line->linked_type === 'competitor' ? 'Battle card shown' : $line->linked_type }} · {{ $line->linked_text }}
@endif
@if ($findings->has($line->id))
@foreach ($findings->get($line->id) as $card)
   » DEALLY ({{ strtoupper($card->kind) }}): {{ $card->body }}
@if ($card->package)      [{{ $card->package }}]
@endif
@endforeach
@endif
@empty
No transcript saved for this call.
@endforelse
@if ($queries->isNotEmpty())

============================================
QUESTIONS ASKED DEALLY
============================================
@foreach ($queries as $query)
Q: {{ $query->prompt }}
A: {{ $query->answer ?: '— no answer recorded —' }}
@if ($query->cards)
@foreach ($query->cards as $card)
   » {{ $card['label'] ?? $card['role'] }}: {{ $card['body'] ?? '' }}
@endforeach
@endif

@endforeach
@endif
