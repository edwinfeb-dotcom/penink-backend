<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockedAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'alias',
        'unit_kerja_id',
        'created_by',
    ];

    // Relasi ke Unit Kerja
    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    // Relasi ke User (creator)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}