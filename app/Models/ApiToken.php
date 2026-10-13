<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? ['*'];

        return in_array('*', $abilities) || in_array($ability, $abilities);
    }

    public static function createToken(User $user, string $name, array $abilities = ['*'], ?int $expiresInMinutes = null): array
    {
        $plainText = Str::random(64);

        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => hash('sha256', $plainText),
            'abilities' => $abilities,
            'expires_at' => $expiresInMinutes ? now()->addMinutes($expiresInMinutes) : null,
        ]);

        return [
            'token' => $token,
            'plain_text' => $plainText,
        ];
    }

    public static function findByPlainText(string $plainText): ?static
    {
        return static::where('token', hash('sha256', $plainText))->first();
    }
}
