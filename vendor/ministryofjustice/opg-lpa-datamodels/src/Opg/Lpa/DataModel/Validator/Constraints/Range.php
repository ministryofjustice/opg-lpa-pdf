<?php

namespace Opg\Lpa\DataModel\Validator\Constraints;

use Symfony\Component\Validator\Constraints as SymfonyConstraints;

class Range extends SymfonyConstraints\Range
{
    use ValidatorPathTrait;

    const INVALID_VALUE_ERROR = 1;
    const BEYOND_RANGE_ERROR = 2;
    const BELOW_RANGE_ERROR = 3;

    protected static $errorNames = [
        self::INVALID_VALUE_ERROR => 'INVALID_VALUE_ERROR',
        self::BEYOND_RANGE_ERROR => 'BEYOND_RANGE_ERROR',
        self::BELOW_RANGE_ERROR => 'BELOW_RANGE_ERROR',
    ];

    public $minMessage = 'must-be-greater-than-or-equal:{{ limit }}';
    public $maxMessage = 'must-be-less-than-or-equal:{{ limit }}';
    public $invalidMessage = 'expected-type:number';
}
