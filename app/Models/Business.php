<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $webhook_url
 * @property \Carbon\CarbonImmutable|null $verified_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ApiToken> $apiTokens
 * @property-read int|null $api_tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Account> $ledgerAccounts
 * @property-read int|null $accounts_count
 * @property-read User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\BusinessFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereWebhookUrl($value)
 *
 * @mixin \Eloquent
 */
final class Business extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\BusinessFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasOneTimePasswords;

    protected $fillable = [
        'name',
        'email',
        'owner_id',
        'webhook_url',
        'verified_at',
        'account_number',
        'account_name',
        'bank_code',
        'currency',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function ledgerAccounts(): MorphMany
    {
        return $this->morphMany(Account::class, 'holder');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return HasMany<ApiToken, Business>
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
        ];
    }
}
