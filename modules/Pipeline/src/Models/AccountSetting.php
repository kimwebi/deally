<?php

namespace Deally\Pipeline\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-level account settings. A tenant owns a single row, keyed like the
 * retention settings row. The demand threshold decides which customers count
 * as Demand Accounts in the risk engine.
 */
class AccountSetting extends Model
{
    protected $connection = 'deally';

    protected $fillable = [
        'demand_pipeline_threshold',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            ['demand_pipeline_threshold' => 150000]
        );
    }

    public function demandThreshold(): int
    {
        return (int) $this->demand_pipeline_threshold;
    }
}
