<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live AI assistant · DeAlly Docs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@200;300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="ambient"></div>


<div id="docs" class="screen active">
    <div class="docs-card">
        <a href="{{ route('login') }}" class="docs-back">← Sign in</a>

        <div class="login-logo"><div class="bolt"><i class="bi bi-lightning-fill"></i></div>DeAlly</div>
        <div class="login-sub">Live AI assistant — configuration guide</div>
        <a href="{{ route('docs.overview') }}" class="docs-back" style="margin-top:14px;">Product overview →</a>

        <div class="docs-section">
            <div class="docs-heading">How it works</div>
            <p class="docs-text">
                During a live call, audio chunks from the mic are sent to the server. Each chunk is transcribed with
                OpenAI Whisper, matched against the tenant knowledge base, and the suggested replies land on the
                Findings panel in near real time.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Driver selection</div>
            <p class="docs-text">The server picks the driver automatically at runtime:</p>
            <ul class="docs-list">
                <li>OpenAI <span class="font-mono">LiveAssistant</span> when <span class="font-mono">OPENAI_API_KEY</span> is set.</li>
                <li>Simulated <span class="font-mono">DummyAssistant</span> otherwise — the live UI still works for demos.</li>
                <li>Swapping providers only requires implementing the same three methods (transcribe, suggest, answer).</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Configuration</div>
            <table class="docs-table">
                <thead>
                    <tr><th>Variable</th><th>Default</th><th>Purpose</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="font-mono">OPENAI_API_KEY</span></td><td>—</td><td>Enables the live driver.</td></tr>
                    <tr><td><span class="font-mono">OPENAI_TRANSCRIPTION_MODEL</span></td><td><span class="font-mono">whisper-1</span></td><td>Audio transcription.</td></tr>
                    <tr><td><span class="font-mono">OPENAI_CHAT_MODEL</span></td><td><span class="font-mono">gpt-4o-mini</span></td><td>Suggested replies.</td></tr>
                    <tr><td><span class="font-mono">OPENAI_TIMEOUT</span></td><td><span class="font-mono">45</span></td><td>Request timeout (seconds).</td></tr>
                </tbody>
            </table>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Endpoints</div>
            <div class="code-block mono"><span class="code-method">POST</span> /app/calls/{call}/live/transcribe</div>
            <p class="docs-text">Multipart upload, <span class="font-mono">audio</span> field (max 10 MB). Returns the transcript plus suggested cards.</p>
            <div class="code-block mono" style="margin-top:10px;"><span class="code-method">POST</span> /app/calls/{call}/live/query</div>
            <p class="docs-text">JSON body with <span class="font-mono">text</span> (max 1000 chars). Returns an answer plus cards.</p>
        </div>
    </div>
</div>

</body>
</html>