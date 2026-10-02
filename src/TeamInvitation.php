<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class TeamInvitation extends Model
{
    use HasUuids;

    protected $table = 'sm_team_invitations';

    protected $fillable = [
        'team_id',
        'email',
        'invited_by',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'invited_by');
    }

    public function isFor(Person $person): bool
    {
        return $person->email !== null && $this->email === Str::lower($person->email);
    }

    public function scopeFor(Builder $query, Person $person): void
    {
        $query->where('email', Str::lower((string) $person->email))->whereHas('team');
    }
}
