<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class MatchAvailability extends Model
{
    use HasUuids;

    protected $table = 'sm_match_availabilities';

    protected $fillable = [
        'match_id',
        'team_member_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AvailabilityStatus::class,
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TeamMatch::class, 'match_id');
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
