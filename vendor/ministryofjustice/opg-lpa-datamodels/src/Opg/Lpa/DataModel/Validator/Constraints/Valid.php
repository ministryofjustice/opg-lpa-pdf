<?php

namespace Opg\Lpa\DataModel\Validator\Constraints;

use Symfony\Component\Validator\Constraints as SymfonyConstraints;

class Valid extends SymfonyConstraints\Valid
{
    use ValidatorPathTrait;
}
