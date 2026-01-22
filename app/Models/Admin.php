<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the admin's sessions.
     */
    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Get the admin's full name.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);
        return implode(' ', $parts);
    }

    /**
     * Return admin data for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'role' => 'admin', // Static role since all are admins
        ];
    }
}
