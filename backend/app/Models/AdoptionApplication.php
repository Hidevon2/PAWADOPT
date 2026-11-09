<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdoptionApplication extends Model
{
    protected $fillable = ['user_id', 'pet_id', 'phone', 'city', 'housing', 'experience', 'message', 'status', 'reference'];
    public function pet(): BelongsTo { return $this->belongsTo(Pet::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
