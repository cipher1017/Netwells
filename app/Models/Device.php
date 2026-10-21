<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'mac',
        'last_ip',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Normalise a MAC to uppercase colon-separated form: AA:BB:CC:DD:EE:FF.
     * Returns null if the input does not contain 12 hex digits.
     */
    public static function normaliseMac(?string $mac): ?string
    {
        if ($mac === null) {
            return null;
        }

        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $mac);

        if (strlen($hex) !== 12) {
            return null;
        }

        return strtoupper(implode(':', str_split($hex, 2)));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }
}
