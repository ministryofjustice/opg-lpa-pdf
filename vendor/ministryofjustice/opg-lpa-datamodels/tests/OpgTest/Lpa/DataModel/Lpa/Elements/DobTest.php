<?php

namespace OpgTest\Lpa\DataModel\Lpa\Elements;

use Opg\Lpa\DataModel\Common\Dob;
use OpgTest\Lpa\DataModel\FixturesData;
use OpgTest\Lpa\DataModel\TestHelper;

class DobTest extends \PHPUnit_Framework_TestCase
{
    public function testValidation()
    {
        $donor = FixturesData::getDonor();
        /* @var $dob \Opg\Lpa\DataModel\Common\Dob */
        $dob = $donor->get('dob');

        $validatorResponse = $dob->validate();
        $this->assertFalse($validatorResponse->hasErrors());
    }

    public function testValidationFailed()
    {
        $dob = new Dob();

        $validatorResponse = $dob->validate();
        $this->assertTrue($validatorResponse->hasErrors());
        $errors = $validatorResponse->getArrayCopy();
        $this->assertEquals(1, count($errors));
        TestHelper::assertNoDuplicateErrorMessages($errors, $this);
        $this->assertNotNull($errors['date']);
        $this->assertEquals('cannot-be-blank', $errors['date']['messages'][0]);
    }

    public function testValidationFailedInFuture()
    {
        $dob = new Dob();
        $dob->set('date', new \DateTime('2199-01-01'));

        $validatorResponse = $dob->validate();
        $this->assertTrue($validatorResponse->hasErrors());
        $errors = $validatorResponse->getArrayCopy();
        $this->assertEquals(1, count($errors));
        TestHelper::assertNoDuplicateErrorMessages($errors, $this);
        $this->assertNotNull($errors['date']);
        $this->assertEquals('must-be-less-than-or-equal-to-today', $errors['date']['messages'][0]);
    }
}