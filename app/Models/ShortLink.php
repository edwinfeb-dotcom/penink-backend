<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UnitKerja;
use App\Models\ShortLinkClick;

class ShortLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_url',
        'short_code',
        'type',
        'title',
        'click_count',
        'expires_at',
        'status',
        'locked_unit_kerja_id', // <--- TAMBAHAN BARU
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'status' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clicks()
    {
        return $this->hasMany(ShortLinkClick::class);
    }

    // <--- TAMBAHAN BARU: Relasi ke Unit Kerja yang dikunci
    public function lockedUnitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'locked_unit_kerja_id');
    }
}