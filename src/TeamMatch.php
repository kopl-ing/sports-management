<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;

class TeamMatch extends Model
{
    use HasUuids;

    protected $table = 'sm_matches';

    protected $fillable = [
        'team_id',
        'opponent_name',
        'home_away',
        'location_address',
        'format_preset_id',
        'play_minutes',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'home_away' => HomeAway::class,
            'scheduled_at' => 'datetime',
            'play_minutes' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function formatPreset(): BelongsTo
    {
        return $this->belongsTo(TeamFormatPreset::class, 'format_preset_id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(MatchAvailability::class, 'match_id');
    }

    public function lineup(): HasMany
    {
        return $this->hasMany(MatchLineup::class, 'match_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(MatchPeriod::class, 'match_id')->orderBy('sequence');
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(MatchSubstitution::class, 'match_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(MatchGoal::class, 'match_id');
    }

    public function timeline(): MatchTimeline
    {
        $this->loadMissing(['periods', 'substitutions', 'goals']);

        return new MatchTimeline($this->periods, $this->substitutions, $this->goals);
    }

    /**
     * @return array<int, string>
     */
    public function absentMemberIds(): array
    {
        return $this->availabilities()->where('status', AvailabilityStatus::Absent)->pluck('team_member_id')->all();
    }

    public function effectiveFormatPreset(): ?TeamFormatPreset
    {
        return $this->formatPreset ?? $this->team->formatPreset;
    }

    public function effectivePlayMinutes(): ?int
    {
        return $this->play_minutes ?? $this->effectiveFormatPreset()?->play_minutes;
    }

    /**
     * Play time each of `$squadSize` players gets when the field is shared equally, in whole minutes so a badge turns green on the minute it shows.
     */
    public function fairShareSeconds(int $squadSize): ?int
    {
        $minutes = $this->effectivePlayMinutes();
        $onField = $this->effectiveFormatPreset()?->players_on_field;

        if ($minutes === null || $onField === null || $squadSize === 0) {
            return null;
        }

        return intdiv($minutes * min($onField, $squadSize), $squadSize) * 60;
    }

    public function fairBenchSeconds(int $squadSize): ?int
    {
        $fairShare = $this->fairShareSeconds($squadSize);

        return $fairShare === null ? null : $this->effectivePlayMinutes() * 60 - $fairShare;
    }
}
