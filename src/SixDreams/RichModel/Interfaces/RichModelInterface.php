<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Interfaces;

/**
 * Interface RichModelInterface
 * Used only as constant storage.
 */
interface RichModelInterface
{
    // Declaring this in richAccessMap, will throw exception, if some one try access not defined field in array.
    public const RICH_STRICT = '+strict';

    // Declaring this in richAccessMap will disable write (usable for DTO).
    public const RICH_READONLY = '+readonly';

    // Static field with access map.
    public const RICH_MAP_NAME = 'richAccessMap';

    public const RICH_FIELD_NAMES = [
        'richAccessMap'       => null,
        'richAccessMapExists' => null,
        'richClassReflection' => null,
        'richAccessMapArray'  => null,
        'richIsReadOnly'      => null
    ];
}
