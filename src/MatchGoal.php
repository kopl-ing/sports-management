<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class MatchGoal extends Model
{
    use HasUuids;

    protected $table = 'sm_match_goals';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'match_id',
        'period_id',
        'opponent',
        'own_goal',
        'points',
        'scorer_team_member_id',
        'assist_team_member_id',
        'offset_seconds',
    ];

    protected function casts(): array
    {
        return [
            'opponent' => 'boolean',
            'own_goal' => 'boolean',
            'points' => 'integer',
            'offset_seconds' => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MatchPeriod::class, 'period_id');
    }

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'scorer_team_member_id');
    }

    public function assist(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assist_team_member_id');
    }
}
