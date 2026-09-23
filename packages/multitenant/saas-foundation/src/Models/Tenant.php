<?php

namespace SaasFoundation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'suspended_at',
        'timezone',
        'locale',
        'currency',
        'metadata',
        'settings',
        'number',
        'provisioning_status',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'metadata' => 'array',
            'settings' => 'array',
        ];
    }

    public function assignNumber(): void
    {
        if ($this->number !== null) {
            return;
        }

        $max = static::query()->lockForUpdate()->max('number');

        $this->number = (int) $max + 1;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tenant $tenant): void {
            $tenant->assignNumber();

            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            Membership::class,
            'tenant_id',
            'id',
            'id',
            'user_id'
        );
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL;
    }

    public function canAccess(string $permission): bool
    {
        return $this->isActive();
    }

    public function hasFeature(string $featureSlug): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->whereHas('items.feature', function (Builder $query) use ($featureSlug): void {
                $query->where('slug', $featureSlug);
            })
            ->exists();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public function setSetting(string $key, mixed $value, string $type = 'string'): TenantSetting
    {
        return $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type],
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
