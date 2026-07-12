<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'county_id',
    ];

    /**
     * The county this city belongs to.
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }
}
