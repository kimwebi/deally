<?php

namespace Deally\Calls\Models;

use Database\Factories\MeetingPlatformFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A meeting platform this company has enabled.
 *
 * `connected` is the honest part: until an operator configures credentials for
 * a platform, it can be enabled for visibility but cannot create a meeting or
 * admit a bot, and the UI says so rather than offering a link that goes nowhere.
 */
class MeetingPlatform extends Model
{
    /** @use HasFactory<MeetingPlatformFactory> */
    use HasFactory;

    protected $connection = 'deally';

    protected $fillable = [
        'key',
        'name',
        'icon',
        'enabled',
        'connected',
        'connection_note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'connected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'meeting_platform', 'key');
    }

    public function isUsable(): bool
    {
        return $this->enabled && $this->connected;
    }
}
