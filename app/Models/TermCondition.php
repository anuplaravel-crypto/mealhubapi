<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TermCondition extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'title',
        'content',
        'version',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * The admin who authored this terms & conditions document.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The users who have accepted this document (via the term_condition_users pivot).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'term_condition_users')
            ->withPivot('accepted_at', 'ip_address')
            ->withTimestamps();
    }

    /**
     * Scope to the active terms & conditions for a given role.
     */
    public function scopeActiveForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role)
            ->where('is_active', true)
            ->latest('version');
    }
}
