<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancelledWeekoff extends Model
{
    protected $fillable = ['date', 'created_by', 'reason'];
}