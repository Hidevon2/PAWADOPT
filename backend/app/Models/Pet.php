<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['traits' => 'array', 'age_months' => 'integer', 'adoption_fee' => 'integer']; }
}
