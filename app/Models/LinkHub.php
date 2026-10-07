<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UnitKerja;

class LinkHub extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'short_code',
        'type',
        'status',
        'locked_unit_kerja_id', // <--- TAMBAHAN BARU
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(LinkHubItem::class)->orderBy('sort_order');
    }

    // <--- TAMBAHAN BARU: Relasi ke Unit Kerja yang dikunci
    public function lockedUnitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'locked_unit_kerja_id');
    }
}