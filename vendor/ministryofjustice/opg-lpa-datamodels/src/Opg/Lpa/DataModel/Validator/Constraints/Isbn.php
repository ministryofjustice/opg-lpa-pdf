<?php

namespace Opg\Lpa\DataModel\Validator\Constraints;

use Symfony\Component\Validator\Constraints as SymfonyConstraints;

class Isbn extends SymfonyConstraints\Isbn
{
    use ValidatorPathTrait;

    const TOO_SHORT_ERROR = 1;
    const TOO_LONG_ERROR = 2;
    const INVALID_CHARACTERS_ERROR = 3;
    const CHECKSUM_FAILED_ERROR = 4;
    const TYPE_NOT_RECOGNIZED_ERROR = 5;

    protected static $errorNames = [
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::CHECKSUM_FAILED_ERROR => 'CHECKSUM_FAILED_ERROR',
        self::TYPE_NOT_RECOGNIZED_ERROR => 'TYPE_NOT_RECOGNIZED_ERROR',
    ];

    public $isbn10Message = 'This value is not a valid ISBN-10.';
    public $isbn13Message = 'This value is not a valid ISBN-13.';
    public $bothIsbnMessage = 'This value is neither a valid ISBN-10 nor a valid ISBN-13.';
    public $type;
    public $message;

    public function getDefaultOption()
    {
        return 'type';
    }
}
