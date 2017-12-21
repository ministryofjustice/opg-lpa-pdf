<?php

namespace Opg\Lpa\DataModel\Validator;

interface ValidatableInterface
{
    /**
     * Calls validate(), automatically including all validation groups.
     *
     * @return ValidatorResponse
     */
    public function validateAllGroups();

    /**
     * Validates the concrete class which this method is called on.
     *
     * @param array $properties An array of property names to check. An empty array means all properties.
     * @param array $groups An array of what validator groups to check (if any).
     * @return \Opg\Lpa\DataModel\Validator\ValidatorResponse
     */
    public function validate(array $properties = [], array $groups = []);
}
