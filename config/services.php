<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'whisper-1'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'max_tokens_key' => 'max_tokens',
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
        'ca_bundle' => env('OPENAI_CA_BUNDLE'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'transcription_model' => env('GROQ_TRANSCRIPTION_MODEL', 'whisper-large-v3-turbo'),
        'chat_model' => env('GROQ_CHAT_MODEL', 'openai/gpt-oss-20b'),
        'max_tokens_key' => 'max_completion_tokens',
        'timeout' => (int) env('GROQ_TIMEOUT', 45),
        'ca_bundle' => env('GROQ_CA_BUNDLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Call Assistant
    |--------------------------------------------------------------------------
    |
    | "driver" selects which provider powers the live call assistant. "auto"
    | prefers Groq, then OpenAI, and only falls back to the deterministic
    | demo driver outside of production. Set it explicitly to "dummy" to run
    | the demo transcript, or to "groq"/"openai" to pin a provider.
    |
    | Credentials are always read on the server. Never ship a provider key to
    | the browser — the client only ever sends audio to this application.
    |
    | "ca_bundle" is an optional absolute path to a CA certificate bundle used to
    | verify the provider's TLS certificate. It is only needed where PHP's cURL
    | has no usable trust store, which is the normal case on Windows unless
    | curl.cainfo is set for the SAPI serving the app. Without it, every provider
    | call fails TLS verification and surfaces as a retryable
    | "transcription_unavailable" with no HTTP status. Point it at the same file
    | PHP itself uses, or leave it empty when the platform resolves certificates
    | on its own.
    |
    | "shelf_limit" caps how many findings the live shelf renders at once so a
    | long call cannot flood the panel. Nothing is deleted: only the newest N
    | cards are rendered, and every finding remains on the call for review.
    |
    | "reported_findings" is how many already-reported finding bodies are fed
    | back to the model, so each pass reports what is new instead of repeating
    | the same card while the conversation moves on.
    |
    */

    'live_ai' => [
        'driver' => env('LIVE_AI_DRIVER', 'auto'),
        'analysis_model' => env('LIVE_AI_ANALYSIS_MODEL'),
        'analysis_window' => (int) env('LIVE_AI_ANALYSIS_WINDOW', 8),
        'analysis_min_interval' => (int) env('LIVE_AI_ANALYSIS_MIN_INTERVAL', 10),
        'shelf_limit' => (int) env('LIVE_AI_SHELF_LIMIT', 10),
        'reported_findings' => (int) env('LIVE_AI_REPORTED_FINDINGS', 6),
    ],

    /*
     | Meeting platforms.
     |
     | No platform is connected by default, and that is the honest state: the
     | initiation flow exists and runs, but creating a meeting or admitting a
     | transcription bot needs a real OAuth app and bot credentials, which cannot
     | be simulated. A connector implements Deally\Calls\Contracts\MeetingPlatformConnector
     | and returns real identifiers; until one is registered every platform
     | reports itself unavailable and the UI says so, rather than offering a join
     | link that resolves to nothing.
     |
     | Each key below is the credential set a connector for that platform reads.
     */
    'meetings' => [
        'zoom' => [
            'account_id' => env('ZOOM_ACCOUNT_ID'),
            'client_id' => env('ZOOM_CLIENT_ID'),
            'client_secret' => env('ZOOM_CLIENT_SECRET'),
        ],

        'microsoft_teams' => [
            'tenant_id' => env('MICROSOFT_TENANT_ID'),
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        ],

        'google_meet' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        ],
    ],

];
