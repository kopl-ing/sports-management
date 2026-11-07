<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

/**
 * Players on the field agreed with the opponent during the match, from that moment on.
 */
class MatchFieldSize extends Model
{
    use HasUuids;

    protected $table = 'sm_match_field_sizes';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'match_id',
        'period_id',
        'offset_seconds',
        'players_on_field',
    ];

    protected function casts(): array
    {
        return [
            'offset_seconds' => 'integer',
            'players_on_field' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MatchPeriod::class, 'period_id');
    }
}
