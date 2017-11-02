<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Exception;

/**
 * Class RichModelFieldException
 *
 * @package SixDreams\RichModel\Exception
 */
class RichModelFieldException extends \Exception
{
    /**
     * RichModelFieldException constructor.
     *
     * @param string $text
     */
    public function __construct(string $text)
    {
        parent::__construct($text);
    }
}
