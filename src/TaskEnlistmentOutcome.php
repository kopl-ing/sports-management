<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class TaskEnlistmentOutcome extends Model
{
    use HasUuids;

    protected $table = 'sm_task_outcomes';

    protected $fillable = [
        'enlistment_id',
        'occurrence_id',
        'outcome',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => TaskOutcome::class,
        ];
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'confirmed_by');
    }
}
