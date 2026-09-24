<div class="proposal-detail">
    @if ($canManage)
        <form method="POST" action="{{ route('deally.proposals.update', $proposal) }}">
            @csrf
            @method('PUT')
            <div class="field-block">
                <div class="field-label">Proposal name</div>
                <input class="input-field" name="name" value="{{ $proposal->name }}" required>
            </div>
            <div class="field-block">
                <div class="field-label">Customer</div>
                <div style="font-size: 14px; color: var(--text-2);">{{ $proposal->company }}</div>
            </div>
            <div class="field-block">
                <div class="field-label">Value ($)</div>
                <input class="input-field" type="number" name="value" min="0" step="0.01" value="{{ $proposal->value }}">
            </div>
            <div class="field-block">
                <div class="field-label">Package</div>
                <input class="input-field" name="package" value="{{ $proposal->package }}">
            </div>
            <div class="field-block">
                <div class="field-label">Status</div>
                <select class="input-field" name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($proposal->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-block">
                <div class="field-label">Quote</div>
                <textarea class="input-field" name="quote" rows="3">{{ $proposal->quote }}</textarea>
            </div>
            @if ($proposal->line_items)
                <div class="field-block">
                    <div class="field-label">Line items</div>
                    <div class="proposal-preview" style="margin-top: 6px;">
                        @foreach ((array) $proposal->line_items as $lineItem)
                            <div class="line-item">
                                <span>{{ $lineItem['item'] ?? '' }}</span>
                                <span class="mono">{{ $lineItem['amount'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="modal-footer" style="padding: 0; margin-top: 14px;">
                <button class="btn-sm" type="button" data-close-modal>Close</button>
                <button class="btn-sm primary" type="submit">Save Changes</button>
            </div>
        </form>
    @else
        <div class="field-block">
            <div class="field-label">Proposal</div>
            <div style="font-size: 14px;">{{ $proposal->name }}</div>
        </div>
        <div class="field-block">
            <div class="field-label">Customer</div>
            <div style="font-size: 14px;">{{ $proposal->company }}</div>
        </div>
        <div class="field-block">
            <div class="field-label">Value</div>
            <div class="mono" style="font-size: 14px;">${{ number_format((float) $proposal->value) }}</div>
        </div>
        <div class="field-block">
            <div class="field-label">Status</div>
            <span class="status-pill pending">{{ ucfirst($proposal->status) }}</span>
        </div>
        <div class="modal-footer" style="padding: 0; margin-top: 14px;">
            <button class="btn-sm" type="button" data-close-modal>Close</button>
        </div>
    @endif
</div>