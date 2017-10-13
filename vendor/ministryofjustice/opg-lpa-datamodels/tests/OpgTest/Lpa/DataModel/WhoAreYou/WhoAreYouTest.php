<?php

namespace OpgTest\Lpa\DataModel\WhoAreYou;

use Opg\Lpa\DataModel\WhoAreYou\WhoAreYou;
use OpgTest\Lpa\DataModel\TestHelper;
use PHPUnit\Framework\TestCase;

class WhoAreYouTest extends TestCase
{
    public function testValidationFailed()
    {
        $whoAreYou = new WhoAreYou();

        $validatorResponse = $whoAreYou->validate();
        $this->assertTrue($validatorResponse->hasErrors());
        $errors = $validatorResponse->getArrayCopy();
        $this->assertEquals(1, count($errors));
        TestHelper::assertNoDuplicateErrorMessages($errors, $this);
        $this->assertNotNull($errors['who']);
    }

    public function testValidationFailedWhoSet()
    {
        $whoAreYou = new WhoAreYou();
        $whoAreYou->set('who', 'Test');

        $validatorResponse = $whoAreYou->validate();
        $this->assertTrue($validatorResponse->hasErrors());
        $errors = $validatorResponse->getArrayCopy();
        $this->assertEquals(1, count($errors));
        TestHelper::assertNoDuplicateErrorMessages($errors, $this);
        $this->assertNotNull($errors['who']);
    }

    public function testValidationFailedIncorrectChoice()
    {
        $whoAreYou = new WhoAreYou();
        $whoAreYou->set('who', 'donor');
        $whoAreYou->set('subquestion', 'Incorrect');
        $whoAreYou->set('qualifier', true);

        $validatorResponse = $whoAreYou->validate();
        $this->assertTrue($validatorResponse->hasErrors());
        $errors = $validatorResponse->getArrayCopy();
        $this->assertEquals(2, count($errors));
        TestHelper::assertNoDuplicateErrorMessages($errors, $this);
        $this->assertNotNull($errors['subquestion']);
        $this->assertNotNull($errors['qualifier']);
    }
}
