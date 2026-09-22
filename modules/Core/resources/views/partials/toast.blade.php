<div class="toast" id="toast" @if (session('toast') || session('success')) data-auto-toast="{{ session('toast') ?? session('success') }}" @endif>
    <span class="toast-icon">✓</span>
    <span class="toast-text">{{ session('toast') ?? session('success') ?? 'Action completed' }}</span>
</div>