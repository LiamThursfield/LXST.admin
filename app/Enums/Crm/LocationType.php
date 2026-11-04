<?php

namespace App\Enums\Crm;

use App\Enums\Traits\HasLabels;

/**
 * How customers reach a location, whatever the industry: they visit it, it travels to them, they
 * meet online, or they visit only when they've arranged to.
 */
enum LocationType: string
{
    use HasLabels;

    case Premises = 'premises';
    case Mobile = 'mobile';
    case Online = 'online';
    case ByAppointment = 'by_appointment';

    /**
     * Whether the address is somewhere customers go. A mobile or online business's address may
     * well be someone's home, so it's kept off the public site.
     */
    public function hasPublicAddress(): bool
    {
        return $this === self::Premises || $this === self::ByAppointment;
    }

    /**
     * Whether the location travels to its customers, so it has areas it serves
     */
    public function hasServiceAreas(): bool
    {
        return $this === self::Mobile;
    }

    /**
     * Whether being "open now" means anything. By appointment, the hours only say when
     * appointments can be, not that someone can turn up.
     */
    public function canBeOpenNow(): bool
    {
        return $this !== self::ByAppointment;
    }
}
