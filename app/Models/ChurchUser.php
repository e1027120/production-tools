<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChurchUser extends Pivot
{
    protected $table = 'church_user';

    protected $casts = [
        'modules' => 'array',
    ];
}
