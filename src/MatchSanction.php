<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class MatchSanction extends Model
{
    use HasUuids;

    protected $table = 'sm_match_sanctions';

    protected $fillable = [
        'match_id',
        'period_id',
        'team_member_id',
        'kind',
        'offset_seconds',
        'duration_seconds',
        'substitution_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SanctionKind::class,
            'offset_seconds' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MatchPeriod::class, 'period_id');
    }

    public function substitution(): BelongsTo
    {
        return $this->belongsTo(MatchSubstitution::class, 'substitution_id');
    }
}
