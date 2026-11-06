<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class TeamPeriod extends Model
{
    use HasUuids;

    protected $table = 'sm_team_periods';

    protected $fillable = [
        'team_id',
        'name',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->starts_on, $this->ends_on);
    }
}
