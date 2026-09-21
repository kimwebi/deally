<?php

namespace Deally\Retention\Models;

use Illuminate\Database\Eloquent\Model;

class RetentionSetting extends Model
{
    protected $connection = 'deally';

    protected $fillable = [
        'tier',
        'months',
    ];
}
