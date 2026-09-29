<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

class MatchPeriod extends Model
{
    use HasUuids;

    protected $table = 'sm_match_periods';

    protected $fillable = [
        'match_id',
        'sequence',
        'type',
        'started_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'type' => PeriodType::class,
            'started_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TeamMatch::class, 'match_id');
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(MatchSubstitution::class, 'period_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(MatchGoal::class, 'period_id');
    }

    public function isRunning(): bool
    {
        return $this->started_at !== null && $this->duration_seconds === null;
    }
}
