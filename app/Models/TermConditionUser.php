<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermConditionUser extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'term_condition_id',
        'accepted_at',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function termCondition(): BelongsTo
    {
        return $this->belongsTo(TermCondition::class);
    }
}
