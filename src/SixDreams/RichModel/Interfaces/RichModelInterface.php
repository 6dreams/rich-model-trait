<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Interfaces;

/**
 * Interface RichModelInterface
 *
 * Используется для хранения констант для RichModelTrait. Так же является маркером, сообщающим о том, что сущность умеет __set.
 *
 * @package SixDreams\RichModel\Interfaces
 */
interface RichModelInterface
{
    // Обязательно должен быть ключ в массиве.
    public const RICH_STRICT = '_rich_strict';
}
