<?php

namespace SzentirasHu\Data\Entity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $key_hash
 * @property string|null $key_plain
 * @property string $key_prefix
 * @property bool $is_internal
 * @property bool $is_self_service
 * @property int|null $throttle_rate
 * @property bool $enabled
 * @property int|null $created_by_anonymous_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read AnonymousId|null $createdBy
 * @property string|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereCreatedByAnonymousId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereIsInternal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereKeyHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereKeyPrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereThrottleRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiKey whereUsageCount($value)
 * @mixin \Eloquent
 */
class ApiKey extends Model
{
    use HasFactory;

    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key_hash',
        'key_plain',
        'key_prefix',
        'is_internal',
        'is_self_service',
        'throttle_rate',
        'enabled',
        'created_by_anonymous_id',
        'description',
        'last_used_at',
        'usage_count',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_self_service' => 'boolean',
        'enabled' => 'boolean',
        'throttle_rate' => 'integer',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * The anonymous user (editor) who created this key.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AnonymousId::class, 'created_by_anonymous_id');
    }

    /**
     * Determine if this key is considered internal (no throttling).
     */
    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    /**
     * Determine if this key is considered external (subject to throttling).
     */
    public function isExternal(): bool
    {
        return !$this->is_internal;
    }

    /**
     * Determine if this key was created by a user through the self-service flow.
     */
    public function isSelfService(): bool
    {
        return $this->is_self_service;
    }

    /**
     * Get the effective throttle rate (requests per minute).
     * Returns null for unlimited (internal keys) or explicit null.
     */
    public function effectiveThrottleRate(): ?int
    {
        if ($this->isInternal()) {
            return config('api.internal_throttle', null);
        }

        return $this->throttle_rate ?? config('api.default_throttle', 60);
    }
}
