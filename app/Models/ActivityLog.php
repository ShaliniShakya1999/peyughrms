<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'ip_address',
        'browser_agent',
    ];

    /**
     * Get the user who performed the activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
