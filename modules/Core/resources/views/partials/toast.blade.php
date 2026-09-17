<div class="toast" id="toast" @if (session('toast')) data-auto-toast="{{ session('toast') }}" @endif>
    <span class="toast-icon">✓</span>
    <span class="toast-text">{{ session('toast') ?? 'Action completed' }}</span>
</div>