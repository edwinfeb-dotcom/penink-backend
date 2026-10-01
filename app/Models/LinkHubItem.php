<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkHubItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'link_hub_id',
        'title',
        'url',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function linkHub()
    {
        return $this->belongsTo(LinkHub::class);
    }
}