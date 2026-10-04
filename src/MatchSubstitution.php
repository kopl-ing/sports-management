<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class MatchSubstitution extends Model
{
    use HasUuids;

    protected $table = 'sm_match_substitutions';

    /** Same-second events replay in creation order (`MatchTimeline::replay()`), so keep microseconds. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'match_id',
        'period_id',
        'team_member_id',
        'direction',
        'zone',
        'offset_seconds',
    ];

    protected function casts(): array
    {
        return [
            'direction' => SubstitutionDirection::class,
            'zone' => Position::class,
            'offset_seconds' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MatchPeriod::class, 'period_id');
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
