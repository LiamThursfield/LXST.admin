<?php

namespace App\Enums\Tracker;

use App\Enums\Traits\HasLabels;

enum TrackerUnit: string
{
    use HasLabels;

    case Boolean = 'boolean';
    case Count = 'count';
    case Kilograms = 'kg';
    case Pounds = 'lb';
    case Litres = 'l';
    case Millilitres = 'ml';
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Kilocalories = 'kcal';
    case TimeOfDay = 'time-of-day';

    /**
     * The measure (dimension) this unit belongs to.
     */
    public function measure(): TrackerMeasure
    {
        return match ($this) {
            self::Boolean => TrackerMeasure::Boolean,
            self::Count => TrackerMeasure::Count,
            self::Kilograms, self::Pounds => TrackerMeasure::Weight,
            self::Litres, self::Millilitres => TrackerMeasure::Volume,
            self::Minutes, self::Hours => TrackerMeasure::Duration,
            self::Kilocalories => TrackerMeasure::Energy,
            self::TimeOfDay => TrackerMeasure::ClockTime,
        };
    }

    /**
     * The multiplier that converts a value in this unit into the measure's
     * canonical base unit (grams, millilitres, seconds, kcal, etc.).
     */
    public function toBaseFactor(): float
    {
        return match ($this) {
            self::Kilograms => 1000.0,
            self::Pounds => 453.59237,
            self::Litres => 1000.0,
            self::Minutes => 60.0,
            self::Hours => 3600.0,
            self::Boolean,
            self::Count,
            self::Millilitres,
            self::Kilocalories,
            self::TimeOfDay => 1.0,
        };
    }

    /**
     * Convert a display value in this unit into the canonical base unit for storage.
     */
    public function toBase(float $value): float
    {
        return $value * $this->toBaseFactor();
    }

    /**
     * Convert a stored base-unit value back into this display unit.
     */
    public function fromBase(float $value): float
    {
        return $value / $this->toBaseFactor();
    }
}
