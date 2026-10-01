<?php

namespace App\Models;
use App\Models\User;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks';


    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}