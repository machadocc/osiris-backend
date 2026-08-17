<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['endpoint', 'p256dh', 'auth'])]
class PushSubscription extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
