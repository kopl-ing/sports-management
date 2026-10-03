<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Sport;

use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\SanctionKind;
use Kopling\SportsManagement\TeamFormatPreset;

/**
 * Behaviour per sport; the numbers live on the format preset, whose `rules` override `rules()` here.
 */
abstract class SportConfig
{
    /**
     * @return array<int, Position> field rows, top to bottom
     */
    abstract protected function rows(): array;

    abstract public function defaultZone(): Position;

    protected function keeper(): ?Position
    {
        return null;
    }

    /**
     * @return array<string, int|null>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, SanctionKind>
     */
    public function sanctions(): array
    {
        return [];
    }

    /**
     * @return array<int, int>
     */
    public function pointValues(): array
    {
        return [1];
    }

    public function rule(?TeamFormatPreset $preset, string $key): ?int
    {
        $rules = $preset?->rules ?? [];

        return array_key_exists($key, $rules) ? $rules[$key] : ($this->rules()[$key] ?? null);
    }

    public function keeperZone(?TeamFormatPreset $preset = null): ?Position
    {
        return $this->rule($preset, 'keeper') === 0 ? null : $this->keeper();
    }

    /**
     * @return array<int, Position>
     */
    public function zones(?TeamFormatPreset $preset = null): array
    {
        $keeper = $this->keeper();

        return $keeper !== null && $this->keeperZone($preset) === null
            ? array_values(array_filter($this->rows(), fn (Position $zone) => $zone !== $keeper))
            : $this->rows();
    }
}
