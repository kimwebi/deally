<?php

namespace SaasFoundation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SaasFoundation\Models\UsageRecord;

class ResetUsagePeriod implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?string $period = null,
    ) {}

    public function handle(): void
    {
        $period = $this->period ?? now()->format('Y-m');

        UsageRecord::where('period', $period)->update(['usage' => 0]);
    }
}
