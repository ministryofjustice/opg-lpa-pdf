<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Document;
use Opg\Lpa\DataModel\Lpa\Elements\Name;
use Opg\Lpa\DataModel\Lpa\Elements\EmailAddress;

class Cs1 extends AbstractCsForm
{
    use AttorneysTrait;

    /**
     * Short ref for this continuation sheet
     *
     * @var
     */
    protected $csRef = 'cs1';

    /**
     * Filename of the PDF document to use
     *
     * @var
     */
    protected $pdfTemplateFilename = 'LPC_Continuation_Sheet_1.pdf';

    /**
     * Generate the required continuation sheet(s)
     *
     * @return array
     */
    public function generate()
    {
        $this->logStartMessage();

        //  Define a mapping for the actor type to the number of actors allowed on the standard form
        $actorTypesMaxCounts = [
            'primaryAttorneys'     => Lp1::MAX_ATTORNEYS_ON_STANDARD_FORM,
            'replacementAttorneys' => Lp1::MAX_REPLACEMENT_ATTORNEYS_ON_STANDARD_FORM,
            'peopleToNotify'       => Lp1::MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM
        ];

        $additionalActors = [];

        //  Loop through the actor types, obtain the details from the LPA data and determine how many "additional" parties there are
        foreach ($actorTypesMaxCounts as $actorType => $actorTypeMaxCount) {
            //  Get the required actors from the LPA data and the max number of actors on the standard LPA pages
            $actors = $this->lpa->document->$actorType;

            //  If the number of actors is not above the maximum then move to the next actor type
            if (count($actors) <= $actorTypeMaxCount) {
                continue;
            }

            //  If this is a P&F LPA and this is one of the list of attorneys, sort the data so that the trust is first
            if ($this->lpa->document->type == Document::LPA_TYPE_PF && in_array($actorType, ['primaryAttorneys', 'replacementAttorneys'])) {
                $actors = $this->sortAttorneys($actorType);
            }

            sort($actors);

            for ($i = $actorTypeMaxCount; $i < count($actors); $i++) {
                $additionalActors[] = [
                    'person' => $actors[$i],
                    'type'   => $actorType
                ];
            }
        }

        //  Loop through the additional actors and generate the required PDFs
        foreach ($additionalActors as $idx => $additionalActor) {
            $pIdx = ($idx % 2);

            if ($pIdx == 0) {
                $this->addFormData('cs1-donor-full-name', $this->fullName($this->lpa->document->donor->name))
                     ->addFormData('cs1-footer-right', $this->getFooter());
            }

            //  Determine the value for the actor type checkbox - for the attorneys remove the trailing 's'
            $actorTypeCheckbox = $additionalActor['type'];

            if (strpos($actorTypeCheckbox, 'Attorneys') > 0) {
                $actorTypeCheckbox = str_replace('Attorneys', 'Attorney', $actorTypeCheckbox);
            }

            $this->addFormData('cs1-' . $pIdx . '-is', $actorTypeCheckbox);

            $actor = $additionalActor['person'];

            if ($actor->name instanceof Name) {
                $this->addFormData('cs1-' . $pIdx . '-name-title', $actor->name->title)
                     ->addFormData('cs1-' . $pIdx . '-name-first', $actor->name->first)
                     ->addFormData('cs1-' . $pIdx . '-name-last', $actor->name->last);
            } else {
                $this->addFormData('cs1-' . $pIdx . '-name-last', $actor->name);
            }

            $this->addFormData('cs1-' . $pIdx . '-address-address1', $actor->address->address1)
                 ->addFormData('cs1-' . $pIdx . '-address-address2', $actor->address->address2)
                 ->addFormData('cs1-' . $pIdx . '-address-address3', $actor->address->address3)
                 ->addFormData('cs1-' . $pIdx . '-address-postcode', $actor->address->postcode);

            if (property_exists($actor, 'dob')) {
                $this->addFormData('cs1-' . $pIdx . '-dob-date-day', $actor->dob->date->format('d'))
                     ->addFormData('cs1-' . $pIdx . '-dob-date-month', $actor->dob->date->format('m'))
                     ->addFormData('cs1-' . $pIdx . '-dob-date-year', $actor->dob->date->format('Y'));
            }

            if (property_exists($actor, 'email') && $actor->email instanceof EmailAddress) {
                $this->addFormData('cs1-' . $pIdx . '-email-address', "\n" . $actor->email->address);
            }

            //  If there are 2 actors in the data, or there are no more actors to come then draw the page
            if ($pIdx == 1 || !array_key_exists($idx + 1, $additionalActors)) {
                $filePath = $this->createContinuationSheetPdf();

                //  If appropriate draw the strike through line on the blank inputs
                if ($pIdx == 0) {
                    $this->drawCrossLines($filePath, array(array('cs1')));
                }

                $this->clearFormData();
            }
        }

        return $this->interFileStack;
    }
}