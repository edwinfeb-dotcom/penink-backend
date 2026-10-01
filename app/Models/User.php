<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\UnitKerja;
use App\Models\ShortLink;
use App\Models\Feedback;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'unit_kerja_id',
        'role',
        'user_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    public function unitKerja()
    {
    return $this->belongsTo(UnitKerja::class);
    }

    public function shortLinks()
    {
    return $this->hasMany(ShortLink::class);
    }

    public function auditLogs()
    {
    return $this->hasMany(AuditLog::class);
    }

    public function feedbacks()
    {
    return $this->hasMany(Feedback::class);
    }
}
