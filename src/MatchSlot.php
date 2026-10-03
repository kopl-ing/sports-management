<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Kopling\Core\Database\Model;

class MatchSlot extends Model
{
    use HasUuids;

    protected $table = 'sm_match_slots';

    protected $fillable = [
        'match_id',
        'team_member_id',
        'zone',
        'slot',
    ];

    protected function casts(): array
    {
        return [
            'zone' => Position::class,
            'slot' => 'float',
        ];
    }
}
