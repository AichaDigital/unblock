<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionAudit extends Model
{
    /** @var array<int, string> */
    protected $guarded = ['created_at', 'updated_at'];
}
