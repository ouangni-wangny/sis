<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Domain\Shared\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'name',
        'email',
        'password',
        'pin_hash',
        'matricule',
        'type',
        'statut',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'type' => TypeUser::class,
            'statut' => StatutUser::class,
        ];
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
