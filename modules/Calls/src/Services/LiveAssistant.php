<?php

namespace Deally\Calls\Services;

use Deally\Calls\Contracts\CallAssistant;
use Deally\Calls\Models\Call;
use Deally\Proposals\Models\KnowledgeEntry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveAssistant implements CallAssistant
{
    public const SIGNAL_KINDS = [
        'buying_signal',
        'intent',
        'objection',
        'competitor',
        'risk',
        'knowledge_gap',
    ];

    public const RECOMMENDATION_ROLES = ['say', 'ask', 'reference', 'objection'];

    /**
     * Where a recommendation's content came from. `model` means the assistant
     * answered from its own knowledge, which is welcome for general facts and
     * forbidden for our commercial terms. The rep is told which, because the two
     * carry very different risk.
     *
     * @var array<int, string>
     */
    public const GROUNDINGS = ['kb', 'model'];

    /**
     * Footer used when the model names no real knowledge base entry. A rep still
     * needs to know whether to open something or to commit to a follow-up.
     */
    public const NO_KNOWLEDGE_PACKAGE = 'no KB entry — commit to a follow-up';

    /**
     * Footer for an answer the assistant produced from its own knowledge. There
     * is no document behind it, so the rep is told to verify rather than trust.
     */
    public const MODEL_KNOWLEDGE_PACKAGE = 'answered from model knowledge — verify before quoting';

    /**
     * Stock phrases Whisper produces for audio that carries no speech. Compared
     * after lowercasing and stripping punctuation.
     *
     * @var array<int, string>
     */
    public const HALLUCINATION_PHRASES = [
        'thank you',
        'thank you so much',
        'thank you very much',
        'thanks',
        'thanks a lot',
        'thanks for watching',
        'thanks for watching the video',
        'thank you for watching',
        'you',
        'bye',
        'bye bye',
        'goodbye',
        'hmm',
        'uh',
        'um',
        'huh',
        'oh',
        'ha',
        'haha',
        'silence',
        'inaudible',
        'unintelligible',
        'foreign',
        'no speech',
        'applause',
        'laughter',
        'music',
        'background music',
        'instrumental music',
        'upbeat music',
        'soft music',
        'subtitles by the amara org community',
        'subtitles by amaraorg community',
        'amara org community',
        'transcription by castingwords',
        'transcribed by castingwords',
        'sous titres',
        'sous titres amen',
        'продолжение следует',
        'продолжение',
        'tune in the next one',
        'see you in the next one',
        'see you in the next video',
        'subscribe',
        'like and subscribe',
        'dont forget to subscribe',
        'thanks for listening',
        'please subscribe',
    ];

    /**
     * A recording window holds several seconds of audio, and pure silence makes
     * Whisper emit a stock phrase rather than nothing; those phrases live in
     * HALLUCINATION_PHRASES. A single word is not treated as noise on its own:
     * a backchannel nod like "Mm-hmm." is real speech a rep wants to see, so
     * the bar for speech is one actual word.
     */
    protected const MIN_SPEECH_WORDS = 1;

    /**
     * @var array<string, mixed>
     */
    protected array $provider;

    /**
     * @param  array<string, mixed>|null  $provider
     */
    public function __construct(?array $provider = null)
    {
        $this->provider = $provider ?? (array) config('services.openai', []);
    }

    public function name(): string
    {
        return 'openai';
    }

    public function transcribe(string $audio, string $filename): ?string
    {
        $response = $this->post('/audio/transcriptions', [
            'model' => $this->setting('transcription_model'),
            'response_format' => 'json',
            // Greedy decoding plus an explicit language stop Whisper from
            // drifting languages or inventing filler on audio that holds no
            // speech. No `prompt` is sent: a domain prior is echoed back as
            // transcript text ("B2B sales." landed in a real call), which is
            // worse than the vocabulary bias it was meant to buy.
            'temperature' => 0,
            'language' => 'en',
        ], fn (PendingRequest $client): PendingRequest => $client->attach('file', $audio, $filename));

        $text = trim((string) $response->json('text'));

        return ($text === '' || $this->isHallucination($text)) ? null : $text;
    }

    /**
     * Silence, music, and subtitle tracks make Whisper emit a small set of stock
     * phrases. They are not speech, so they are treated as silence instead of
     * being stored and fed to the analysis window.
     */
    protected function isHallucination(string $text): bool
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text)));
        $normalised = trim((string) preg_replace('/\s+/u', ' ', $normalised));

        // Punctuation-only, digits-only, and single characters are never speech.
        if (! preg_match('/\p{L}/u', $normalised)) {
            return true;
        }

        if (in_array($normalised, self::HALLUCINATION_PHRASES, true)) {
            return true;
        }

        return $this->wordCount($normalised) < self::MIN_SPEECH_WORDS;
    }

    /**
     * Count words in already-normalised text, where words are whitespace
     * separated runs of letters or digits.
     */
    protected function wordCount(string $normalised): int
    {
        $words = preg_split('/\s+/u', $normalised, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? count($words) : 0;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function suggest(Call $call, string $customerText): array
    {
        return $this->attempt('suggestions', function () use ($call, $customerText): array {
            $response = $this->post('/chat/completions', [
                'model' => $this->setting('chat_model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt($call),
                    ],
                    [
                        'role' => 'user',
                        'content' => 'The customer just said: "'.$customerText.'"'.PHP_EOL.PHP_EOL
                            .'Give the agent 2 short suggested replies, one per line, prefixed with "1. " and "2. ". Plain spoken english, no markdown, say the exact words the agent should say.',
                    ],
                ],
                'temperature' => 0.7,
                ...$this->maxTokens(200),
            ]);

            $content = trim((string) $response->json('choices.0.message.content'));

            if ($content === '') {
                return [];
            }

            return collect(explode("\n", $content))
                ->map(fn (string $line): string => trim((string) preg_replace('/^\s*\d+\.\s*/', '', $line)))
                ->filter()
                ->take(2)
                ->values()
                ->all();
        }, []);
    }

    /**
     * @param  array<int, array{id: int, speaker: string, is_agent: bool, text: string}>  $lines
     * @param  array<int, string>  $reported  Finding bodies already on the shelf.
     * @return array{signals: array<int, array<string, mixed>>, recommendations: array<int, array<string, mixed>>}
     */
    public function analyze(Call $call, array $lines, array $reported = []): array
    {
        if ($lines === []) {
            return [
                'signals' => [],
                'recommendations' => [],
                'noticed' => null,
                'proposal_intent' => false,
                'proposal_intent_note' => null,
            ];
        }

        $response = $this->post('/chat/completions', [
            'model' => config('services.live_ai.analysis_model') ?: $this->setting('chat_model'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->analysisPrompt($call),
                ],
                [
                    'role' => 'user',
                    'content' => $this->transcriptWindow($lines).$this->reportedContext($reported),
                ],
            ],
            'temperature' => 0.2,
            /* Reasoning models count their thinking against the same budget as the
               answer. 900 left no room for the document itself: gpt-oss-20b spent
               724 tokens reasoning and was cut off before emitting valid JSON,
               which Groq reports as HTTP 400 "max completion tokens reached
               before generating a valid document". That is a budget problem, not a
               bad request, so the ceiling has to clear reasoning plus answer. */
            ...$this->maxTokens(2400),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'live_call_analysis',
                    'strict' => true,
                    'schema' => $this->analysisSchema(),
                ],
            ],
        ]);

        $content = trim((string) $response->json('choices.0.message.content'));

        return $this->normalizeAnalysis($content, $lines);
    }

    public function answer(Call $call, string $query): ?string
    {
        $recent = $call->transcriptLines()
            ->latest('sequence')
            ->limit(10)
            ->pluck('text')
            ->reverse()
            ->implode(' | ');

        $context = $this->knowledgeContext();
        $recentContext = $recent === '' ? '(no transcript yet)' : $recent;

        return $this->attempt('answer', fn (): ?string => $this->chat(
            [
                [
                    'role' => 'system',
                    'content' => "You are DeAlly, a real-time sales assistant helping an agent on a call with {$call->company}."
                        .' You get the conversation so far and a knowledge base. Answer in plain spoken english with the exact words the agent should say to the customer.'
                        .' Be concise and accurate. Answer general questions — geography, industry norms, definitions, how a product category '
                        .'works — from your own knowledge rather than refusing them; the customer can already hear a non-answer.'
                        .' Never invent our commercial terms: prices, discounts, contract wording, seat limits, SLAs, timelines, or '
                        .'certifications. For those, say you will confirm with the onboarding team rather than guessing.'.PHP_EOL.PHP_EOL
                        .'Knowledge base:'.PHP_EOL.$context.PHP_EOL.PHP_EOL
                        .'Conversation so far: '.$recentContext,
                ],
                [
                    'role' => 'user',
                    'content' => $query,
                ],
            ]
        ), null);
    }

    public function answerGlobal(string $query): ?string
    {
        return $this->attempt('answer', fn (): ?string => $this->chat(
            [
                [
                    'role' => 'system',
                    'content' => 'You are DeAlly, an AI sales enablement assistant. A sales team member asked you a question. '
                        .'Answer in plain spoken english with the exact words they can use with a customer. Be concise and accurate. '
                        .'Answer general questions — geography, industry norms, definitions, how a product category works — from your own '
                        .'knowledge rather than refusing them. Never invent our commercial terms: prices, discounts, contract wording, seat '
                        .'limits, SLAs, timelines, or certifications; for those, say you will confirm with the onboarding team.'
                        .PHP_EOL.PHP_EOL
                        .'Knowledge base:'.PHP_EOL.$this->knowledgeContext(),
                ],
                [
                    'role' => 'user',
                    'content' => $query,
                ],
            ]
        ), null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    protected function chat(array $messages): ?string
    {
        $response = $this->post('/chat/completions', [
            'model' => $this->setting('chat_model'),
            'messages' => $messages,
            'temperature' => 0.7,
            ...$this->maxTokens(200),
        ]);

        $content = trim((string) $response->json('choices.0.message.content'));

        return $content === '' ? null : $content;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(PendingRequest): PendingRequest|null  $with
     *
     * @throws AssistantProviderException
     */
    protected function post(string $path, array $payload, ?callable $with = null): Response
    {
        $this->ensureConfigured();

        $client = $this->client();

        if ($with !== null) {
            $client = $with($client);
        }

        try {
            $response = $client->post($this->endpoint($path), $payload);
        } catch (ConnectionException $exception) {
            /* The transport cause is kept because it never leaves the server: the
               controller answers with a generic error code, while the activity log
               needs to distinguish an unreachable host from a TLS trust store that
               cannot verify the certificate. Both look identical otherwise. */
            throw new AssistantProviderException(
                'The '.$this->name().' provider could not be reached: '.$exception->getMessage(),
                retryable: true,
            );
        }

        if ($response->failed()) {
            /* A reasoning model that runs out of completion budget mid-document is
               reported as HTTP 400, which would otherwise read as a permanent
               client error. It is a budget problem and the same request can succeed
               on a retry, so it is treated as retryable rather than failing the
               call for good. */
            $budgetExhausted = str_contains($response->body(), 'max completion tokens reached');

            throw new AssistantProviderException(
                'The '.$this->name().' provider returned HTTP '.$response->status().'.'
                    .($budgetExhausted ? ' The response budget was exhausted before the answer was complete.' : ''),
                $response->status(),
                $response->serverError() || $response->status() === 429 || $budgetExhausted,
            );
        }

        return $response;
    }

    /**
     * Run a provider call, degrading to a fallback when the provider is down
     * so a live call keeps going instead of returning a 500 mid-sentence.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @param  TValue  $fallback
     * @return TValue
     */
    protected function attempt(string $context, callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (AssistantProviderException $exception) {
            Log::warning('Live call assistant call failed.', [
                'context' => $context,
                'provider' => $this->name(),
                'status' => $exception->status,
                'retryable' => $exception->retryable,
            ]);

            return $fallback;
        }
    }

    protected function client(): PendingRequest
    {
        $client = Http::withToken((string) $this->setting('key'))
            ->acceptJson()
            ->timeout($this->timeout());

        $caBundle = $this->caBundle();

        if ($caBundle !== null) {
            $client = $client->withOptions(['verify' => $caBundle]);
        }

        return $client;
    }

    /**
     * The CA bundle used to verify the provider's certificate, if one is
     * configured and readable.
     *
     * PHP's cURL has no trust store of its own on Windows unless `curl.cainfo`
     * is set for the SAPI actually serving the app, so the same code that works
     * from a shell can fail every web request with cURL error 60. Configuring
     * the bundle here makes verification independent of how PHP was launched.
     */
    protected function caBundle(): ?string
    {
        $path = trim((string) $this->setting('ca_bundle'));

        if ($path === '') {
            return null;
        }

        if (! is_readable($path)) {
            // Loud in the log, harmless to the request: an unreadable bundle is a
            // deployment mistake, and silently ignoring it would restore the very
            // TLS failure this setting exists to prevent.
            Log::warning('Live AI CA bundle is configured but not readable.', [
                'provider' => $this->name(),
                'path' => $path,
            ]);

            return null;
        }

        return $path;
    }

    /**
     * Reasoning models reject `max_tokens` and expect `max_completion_tokens`,
     * so each provider declares which key it speaks.
     *
     * The ceiling is a budget, not a length target, and a reasoning model spends
     * it on thinking before it writes anything.
     *
     * @return array<string, int>
     */
    protected function maxTokens(int $tokens): array
    {
        return [(string) ($this->setting('max_tokens_key') ?: 'max_tokens') => $tokens];
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) $this->setting('base_url'), '/').$path;
    }

    /**
     * @throws AssistantProviderException
     */
    protected function ensureConfigured(): void
    {
        if (filled($this->setting('key'))) {
            return;
        }

        throw new AssistantProviderException(
            'No API key is configured for the '.$this->name().' provider.'
        );
    }

    protected function setting(string $key): mixed
    {
        return $this->provider[$key] ?? null;
    }

    protected function timeout(): int
    {
        return (int) ($this->setting('timeout') ?: 45);
    }

    protected function systemPrompt(Call $call): string
    {
        return 'You are DeAlly, a real-time sales assistant helping an agent on a call with '.$call->company.'.'
            .' Keep suggestions short, specific, and in plain spoken english, with the exact words the agent should say.'
            .' Use the knowledge base below for anything about our products or terms; answer general questions from your own '
            .'knowledge rather than refusing them, and never invent a price, limit, or commitment.'.PHP_EOL.PHP_EOL
            .$this->knowledgeContext();
    }

    protected function analysisPrompt(Call $call): string
    {
        return 'You are DeAlly, analysing a live sales call in progress with '.$call->company.'. '
            .'You are given the most recent transcript lines, each with an integer id, prefixed with the speaker. '
            .'The last line is what matters now: earlier lines are context only. '
            .'Report what that newest line changes for the deal — never invent facts, prices, or commitments.'.PHP_EOL.PHP_EOL
            .'Signals are observations worth surfacing right now:'.PHP_EOL
            .'- buying_signal: the customer leans toward moving forward.'.PHP_EOL
            .'- intent: the customer reveals a goal, need, or priority.'.PHP_EOL
            .'- objection: the customer pushes back on price, timing, risk, or fit.'.PHP_EOL
            .'- competitor: the customer names or implies another vendor.'.PHP_EOL
            .'- risk: a risk to the deal (missing decision maker, silent prospect, no timeline).'.PHP_EOL
            .'- knowledge_gap: the customer asks something the knowledge base cannot answer.'.PHP_EOL.PHP_EOL
            .'A signal "text" must name what is missing or what changed AND the next step for the agent. '
            .'Never restate the customer\'s question back as the finding.'.PHP_EOL.PHP_EOL
            .'When the newest line is the AGENT speaking, judge it the same way: report what the agent just '
            .'committed to, asked, or left unanswered, and what the customer is likely to say next. An agent '
            .'line is not a reason to return an empty result.'.PHP_EOL.PHP_EOL
            .'Recommendations are the exact words the agent should say next, grounded in the knowledge base below.'.PHP_EOL.PHP_EOL
            .'Grounding rules — these matter more than sounding helpful:'.PHP_EOL
            .'- Answer anything a well-informed person would simply know — geography, country and city facts, '
            .'industry norms, definitions, how a product category works, how we compare to competitors — from your '
            .'own knowledge, and set "grounding" to "model". Do not deflect these and do not tell the agent to '
            .'check something you actually know: a confident non-answer wastes the call and costs the agent '
            .'credibility with the customer.'.PHP_EOL
            .'- Never invent anything about our own commercial terms: prices, discounts, contract wording, seat or '
            .'usage limits, SLAs, which features exist, timelines, or certifications. Those are the claims that '
            .'cost a deal when they are wrong, so for them use the knowledge base, or set "grounding" to "kb" and '
            .'commit to a specific follow-up. Never split the difference by guessing.'.PHP_EOL
            .'- Set "grounding" to "kb" when the answer comes from the knowledge base below, and to "model" when it '
            .'comes from your own knowledge. Never mix the two in one item.'.PHP_EOL
            .'Rules:'.PHP_EOL
            .'- "package" must be the title of the knowledge base entry the agent should open for this, copied '
            .'exactly from the list below, or the literal "no KB entry — commit to a follow-up" when nothing '
            .'covers it. Never answer "default", "none", or the signal kind. A "model" item has no document to '
            .'open, so leave "package" as the follow-up literal.'.PHP_EOL
            .'- Never repeat something already reported for this call. Report only what is new.'.PHP_EOL
            .'- Set "source_line" to the id of the transcript line each item came from.'.PHP_EOL
            .'- Set "confidence" between 0 and 1.'.PHP_EOL
            .'- Prefer fewer, high-signal items. An empty array is correct when nothing is happening yet.'.PHP_EOL
            .'- Keep "body" and "text" to short, plain spoken english a rep can say aloud.'.PHP_EOL.PHP_EOL
            .'- "noticed" is what the agent should understand the customer is talking about right now, phrased in your '
            .'own words. It must NOT be a quotation: no quotation marks, no wording copied from the transcript, and '
            .'never the customer\'s sentence with a word changed. Do not restate a question as a question. Write it in '
            .'the present tense as a description of the topic, at most 12 words — for example "weighing us against '
            .'Cisco on support coverage", not "the customer asked about Cisco support". Leave it as an empty string '
            .'when the newest line is not worth summarising, which is usually.'.PHP_EOL.PHP_EOL
            .'- "proposal_intent" is true only when this call has actually agreed that a written proposal, quote, or '
            .'formal pricing document is the next step. A customer asking what it costs is not enough on its own. Put '
            .'the few words describing what it would cover in "proposal_intent_note", or leave that empty.'.PHP_EOL.PHP_EOL
            .'Worked example, answered from your own knowledge:'.PHP_EOL
            .'Customer asks whether the US is considered a third-world country.'.PHP_EOL
            .'recommendation body: "No, the US is not a third-world country. That term describes countries with low '
            .'income and weak infrastructure, and the US is a high-income developed economy."'.PHP_EOL
            .'grounding: "model"'.PHP_EOL
            .'Deflecting that one — "I do not have that information, let me check and get back to you" — is a bad '
            .'answer: the agent can already hear that it is true.'.PHP_EOL.PHP_EOL
            .'Worked example, deliberately not answered:'.PHP_EOL
            .'Customer asks what the Enterprise tier costs for 400 users, and no entry covers volume pricing.'.PHP_EOL
            .'recommendation body: "Let me confirm the 400-user Enterprise pricing properly instead of guessing, and '
            .'I will send it over today."'.PHP_EOL
            .'grounding: "kb"'.PHP_EOL
            .'package: "no KB entry — commit to a follow-up"'.PHP_EOL
            .'Knowledge base:'.PHP_EOL.$this->knowledgeContext();
    }

    /**
     * Findings already on the shelf. Re-reporting them makes the shelf look
     * frozen while the conversation moves on, so the model is told what it has
     * already said.
     *
     * @param  array<int, string>  $reported
     */
    protected function reportedContext(array $reported): string
    {
        $items = collect($reported)
            ->map(fn (string $body): string => trim($body))
            ->filter()
            ->unique()
            ->values();

        if ($items->isEmpty()) {
            return '';
        }

        return PHP_EOL.PHP_EOL.'Already reported for this call — do not repeat these, report only what is new:'.PHP_EOL
            .$items->map(fn (string $body): string => '- '.$body)->implode(PHP_EOL);
    }

    /**
     * @param  array<int, array{id: int, speaker: string, is_agent: bool, text: string}>  $lines
     */
    protected function transcriptWindow(array $lines): string
    {
        return collect($lines)
            ->map(fn (array $line): string => '['.$line['id'].'] '.($line['is_agent'] ? 'Agent' : 'Customer').': '.$line['text'])
            ->implode(PHP_EOL);
    }

    /**
     * @return array<string, mixed>
     */
    protected function analysisSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'signals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => self::SIGNAL_KINDS],
                            'text' => ['type' => 'string'],
                            'quote' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'source_line' => ['type' => 'integer'],
                        ],
                        'required' => ['kind', 'text', 'quote', 'confidence', 'source_line'],
                        'additionalProperties' => false,
                    ],
                ],
                'recommendations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'role' => ['type' => 'string', 'enum' => self::RECOMMENDATION_ROLES],
                            'label' => ['type' => 'string'],
                            'body' => ['type' => 'string'],
                            'package' => ['type' => 'string'],
                            'source' => ['type' => 'string'],
                            'grounding' => ['type' => 'string', 'enum' => self::GROUNDINGS],
                            'confidence' => ['type' => 'number'],
                            'source_line' => ['type' => 'integer'],
                        ],
                        'required' => ['role', 'label', 'body', 'package', 'source', 'grounding', 'confidence', 'source_line'],
                        'additionalProperties' => false,
                    ],
                ],
                'noticed' => ['type' => 'string'],
                'proposal_intent' => ['type' => 'boolean'],
                'proposal_intent_note' => ['type' => 'string'],
            ],
            'required' => ['signals', 'recommendations', 'noticed', 'proposal_intent', 'proposal_intent_note'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Provider output is untrusted input: parse defensively, clamp the
     * enumerations, and drop anything that does not survive validation.
     *
     * @param  array<int, array{id: int, speaker: string, is_agent: bool, text: string}>  $lines
     * @return array{signals: array<int, array<string, mixed>>, recommendations: array<int, array<string, mixed>>, noticed: ?string, proposal_intent: bool, proposal_intent_note: ?string}
     */
    protected function normalizeAnalysis(string $content, array $lines): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AssistantProviderException('The provider returned an unreadable analysis payload.');
        }

        $lineIds = collect($lines)->pluck('id')->map(fn (int $id): int => $id)->all();

        $signals = collect($decoded['signals'] ?? [])
            ->filter(fn (mixed $signal): bool => is_array($signal))
            ->map(fn (array $signal): ?array => $this->normalizeSignal($signal, $lineIds))
            ->filter()
            ->values()
            ->all();

        $recommendations = collect($decoded['recommendations'] ?? [])
            ->filter(fn (mixed $recommendation): bool => is_array($recommendation))
            ->map(fn (array $recommendation): ?array => $this->normalizeRecommendation($recommendation, $lineIds))
            ->filter()
            ->values()
            ->all();

        return [
            'signals' => $signals,
            'recommendations' => $recommendations,
            'noticed' => $this->normalizeParaphrase($decoded['noticed'] ?? null),
            'proposal_intent' => (bool) ($decoded['proposal_intent'] ?? false),
            'proposal_intent_note' => $this->normalizeParaphrase($decoded['proposal_intent_note'] ?? null, 160),
        ];
    }

    /**
     * Keep a model-authored paraphrase inside the bounds the panel assumes.
     *
     * The length cap is not cosmetic: the ephemeral card is a fixed-height slot,
     * so a long paraphrase either clips or pushes the shelf around. Quotation
     * marks are stripped because this is a summary in the agent's own words,
     * and a quoted card reads as the customer having said exactly that.
     */
    protected function normalizeParaphrase(mixed $value, int $maxWords = 12): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/["“”]/u', '', $value) ?? '');

        if ($text === '') {
            return null;
        }

        $words = preg_split('/\s+/u', $text, $maxWords + 1) ?: [];
        $text = trim(implode(' ', array_slice($words, 0, $maxWords)));

        if ($maxWords === 12) {
            $text = rtrim($text, " \t\n\r\0\x0B,;:.-");
        }

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $signal
     * @param  array<int, int>  $lineIds
     * @return array<string, mixed>|null
     */
    protected function normalizeSignal(array $signal, array $lineIds): ?array
    {
        $kind = (string) ($signal['kind'] ?? '');

        if (! in_array($kind, self::SIGNAL_KINDS, true)) {
            return null;
        }

        $text = trim((string) ($signal['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        return [
            'kind' => $kind,
            'text' => $text,
            'quote' => trim((string) ($signal['quote'] ?? '')),
            'confidence' => $this->confidence($signal['confidence'] ?? null),
            'source_line' => $this->sourceLine($signal['source_line'] ?? null, $lineIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  array<int, int>  $lineIds
     * @return array<string, mixed>|null
     */
    protected function normalizeRecommendation(array $recommendation, array $lineIds): ?array
    {
        $role = (string) ($recommendation['role'] ?? '');

        if (! in_array($role, self::RECOMMENDATION_ROLES, true)) {
            return null;
        }

        $body = trim((string) ($recommendation['body'] ?? ''));

        if ($body === '') {
            return null;
        }

        return [
            'role' => $role,
            'label' => trim((string) ($recommendation['label'] ?? '')) ?: ucfirst($role).' this',
            'body' => $body,
            'package' => $this->knowledgePackage($recommendation['package'] ?? '', $this->grounding($recommendation)),
            'source' => trim((string) ($recommendation['source'] ?? '')),
            'grounding' => $this->grounding($recommendation),
            'confidence' => $this->confidence($recommendation['confidence'] ?? null),
            'source_line' => $this->sourceLine($recommendation['source_line'] ?? null, $lineIds),
        ];
    }

    /**
     * Clamp the declared provenance. Provider output is untrusted, so an
     * unrecognised value is treated as `kb` — the conservative reading, since it
     * means "open a document" rather than "trust the model's memory".
     */
    protected function grounding(mixed $value): string
    {
        $grounding = is_array($value)
            ? (string) ($value['grounding'] ?? '')
            : (string) $value;

        return in_array($grounding, self::GROUNDINGS, true) ? $grounding : 'kb';
    }

    /**
     * The card footer is the rep's cue for what to open, so only a real knowledge
     * base entry is rendered as a document. A placeholder such as "default",
     * "general", or the signal kind echoed back tells the rep nothing, so it
     * becomes an explicit follow-up commitment instead.
     *
     * An answer produced from the model's own knowledge has no document behind
     * it at all, so it is labelled as such instead of borrowing the follow-up
     * wording, which would wrongly imply nothing is known.
     */
    protected function knowledgePackage(mixed $value, string $grounding = 'kb'): string
    {
        if ($grounding === 'model') {
            return self::MODEL_KNOWLEDGE_PACKAGE;
        }

        $package = trim((string) $value);

        if ($package !== '' && $this->isKnowledgeTitle($package)) {
            return $package;
        }

        return self::NO_KNOWLEDGE_PACKAGE;
    }

    protected function isKnowledgeTitle(string $value): bool
    {
        $needle = mb_strtolower(trim($value));

        return KnowledgeEntry::query()
            ->pluck('title')
            ->contains(fn (mixed $title): bool => mb_strtolower(trim((string) $title)) === $needle);
    }

    protected function confidence(mixed $value): float
    {
        $confidence = is_numeric($value) ? (float) $value : 0.5;

        return max(0.0, min(1.0, round($confidence, 2)));
    }

    /**
     * @param  array<int, int>  $lineIds
     */
    protected function sourceLine(mixed $value, array $lineIds): ?int
    {
        $id = is_numeric($value) ? (int) $value : null;

        return $id !== null && in_array($id, $lineIds, true) ? $id : null;
    }

    protected function knowledgeContext(): string
    {
        $entries = KnowledgeEntry::query()->get();

        if ($entries->isEmpty()) {
            return '(no knowledge base entries yet)';
        }

        return $entries->map(
            fn (KnowledgeEntry $entry): string => "[{$entry->type}] {$entry->title}: {$entry->description}"
        )->implode(PHP_EOL);
    }
}
