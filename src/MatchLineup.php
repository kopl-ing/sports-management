<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class MatchLineup extends Model
{
    use HasUuids;

    protected $table = 'sm_match_lineups';

    protected $fillable = [
        'match_id',
        'team_member_id',
        'zone',
    ];

    protected function casts(): array
    {
        return [
            'zone' => Position::class,
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
