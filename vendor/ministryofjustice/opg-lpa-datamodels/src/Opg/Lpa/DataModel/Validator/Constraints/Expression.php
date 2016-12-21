<?php
namespace Opg\Lpa\DataModel\Validator\Constraints;

use Symfony\Component\Validator\Constraints as SymfonyConstraints;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Expression extends SymfonyConstraints\Expression
{
    use ValidatorPathTrait;

    public $message = 'This value is not valid.';
    public $expression;

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'expression';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions()
    {
        return array('expression');
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return array(self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT);
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return 'validator.expression';
    }
}
