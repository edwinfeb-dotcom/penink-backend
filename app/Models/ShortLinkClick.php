<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ShortLink;

class ShortLinkClick extends Model
{
    use HasFactory;

    protected $fillable = [
    'short_link_id',
    'clicked_at',
    'source',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function shortLink()
    {
        return $this->belongsTo(ShortLink::class);
    }
}