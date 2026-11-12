<?php

namespace App\Domain\Announcements;

use App\Domain\Display\SectorContext;

enum AnnouncementScope: string
{
    case Global = 'global';
    case Pvc = 'pvc';
    case Aluminium = 'aluminio';

    public function appliesTo(SectorContext $sector): bool
    {
        return $this === self::Global || $this->value === $sector->key;
    }
}
