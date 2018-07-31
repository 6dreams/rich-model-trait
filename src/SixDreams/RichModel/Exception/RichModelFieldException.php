<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Exception;

/**
 * Class RichModelFieldException
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
