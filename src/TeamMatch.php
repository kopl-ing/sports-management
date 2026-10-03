<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;
use Kopling\SportsManagement\Sport\SportConfig;

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

    public function sanctions(): HasMany
    {
        return $this->hasMany(MatchSanction::class, 'match_id');
    }

    /**
     * @return array<int, string> team member ids that may not come on the field right now
     */
    public function unavailableMemberIds(MatchTimeline $timeline): array
    {
        $config = $this->sportConfig();
        $preset = $this->effectiveFormatPreset();
        $out = array_keys($timeline->penaltySecondsLeft());

        foreach ($timeline->sanctionCounts() as $memberId => $counts) {
            foreach ($counts as $kind => $count) {
                $kind = SanctionKind::from($kind);
                $limit = $kind->limitRule() === null ? null : $config->rule($preset, $kind->limitRule());
                if ($kind === SanctionKind::RedCard || ($limit !== null && $count >= $limit)) {
                    $out[] = $memberId;
                }
            }
        }

        return array_values(array_unique($out));
    }

    public function effectiveMaxOnField(MatchTimeline $timeline): ?int
    {
        $max = $this->effectiveFormatPreset()?->players_on_field;

        return $max === null ? null : max(0, $max - $timeline->shortSpells());
    }

    public function slots(): HasMany
    {
        return $this->hasMany(MatchSlot::class, 'match_id');
    }

    /**
     * @return array<string, array{0: Position, 1: float}> stored zone and slot per team member id
     */
    public function slotRows(): array
    {
        return $this->slots->mapWithKeys(fn (MatchSlot $slot) => [$slot->team_member_id => [$slot->zone, $slot->slot]])->all();
    }

    /**
     * @param array<string, Position|null> $placement zone per team member id, before the move
     * @param array{team_member_id: string, zone?: string|null, before_team_member_id?: string|null, replace_team_member_id?: string|null} $data
     */
    public function rememberSlots(array $placement, array $data): void
    {
        $writes = FieldSlots::writes(
            $placement,
            TeamMember::sorted($this->team->members()->with('person')->get())->pluck('id')->all(),
            $this->slotRows(),
            $data['team_member_id'],
            isset($data['zone']) ? Position::from($data['zone']) : null,
            $data['before_team_member_id'] ?? null,
            $data['replace_team_member_id'] ?? null,
        );

        foreach ($writes as $memberId => [$zone, $slot]) {
            $this->slots()->updateOrCreate(['team_member_id' => $memberId], ['zone' => $zone, 'slot' => $slot]);
        }
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
        $this->loadMissing(['periods', 'substitutions', 'goals', 'sanctions']);

        return new MatchTimeline($this->periods, $this->substitutions, $this->goals, breaks: $this->effectiveFormatPreset()?->breaks, sanctions: $this->sanctions);
    }

    /**
     * @return array<int, string>
     */
    public function absentMemberIds(): array
    {
        return $this->availabilities()->where('status', AvailabilityStatus::Absent)->pluck('team_member_id')->all();
    }

    public function sportConfig(): SportConfig
    {
        return $this->team->sport->config();
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

    /**
     * Expected length of each play period, when the format says how many breaks a match has.
     */
    public function playPeriodSeconds(): ?int
    {
        $minutes = $this->effectivePlayMinutes();
        $breaks = $this->effectiveFormatPreset()?->breaks;

        return $minutes === null || $breaks === null ? null : intdiv($minutes * 60, $breaks + 1);
    }

    public function fairBenchSeconds(int $squadSize): ?int
    {
        $fairShare = $this->fairShareSeconds($squadSize);

        return $fairShare === null ? null : $this->effectivePlayMinutes() * 60 - $fairShare;
    }
}
