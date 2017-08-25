<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Common\EmailAddress;
use Opg\Lpa\DataModel\Lpa\Document\Attorneys\TrustCorporation;
use Opg\Lpa\DataModel\Lpa\Document\Decisions\PrimaryAttorneyDecisions;
use Opg\Lpa\DataModel\Lpa\Lpa;

class Lp1f extends AbstractLp1
{
    /**
     * Filename of the PDF template to use
     *
     * @var string
     */
    protected $pdfTemplateFile = 'LP1F.pdf';

    protected function dataMapping()
    {
        parent::dataMapping();

        // Section 2
        $i = 0;

        foreach ($this->sortAttorneys('primaryAttorneys') as $primaryAttorney) {
            if ($primaryAttorney instanceof TrustCorporation) {
                // $i should always be 0
                $this->dataForForm['attorney-' . $i . '-is-trust-corporation'] = self::CHECK_BOX_ON;
                $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-last'] = (string)$primaryAttorney->name;
            } else {
                $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-title'] = $primaryAttorney->name->title;
                $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-first'] = $primaryAttorney->name->first;
                $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-last'] = $primaryAttorney->name->last;

                $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-dob-date-day'] = $primaryAttorney->dob->date->format('d');
                $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-dob-date-month'] = $primaryAttorney->dob->date->format('m');
                $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-dob-date-year'] = $primaryAttorney->dob->date->format('Y');
            }

            $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-address1'] = $primaryAttorney->address->address1;
            $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-address2'] = $primaryAttorney->address->address2;
            $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-address3'] = $primaryAttorney->address->address3;
            $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-postcode'] = $primaryAttorney->address->postcode;

            $this->dataForForm['lpa-document-primaryAttorneys-' . $i . '-email-address'] = ($primaryAttorney->email instanceof EmailAddress ? "\n" . $primaryAttorney->email->address : null);

            if (++$i == self::MAX_ATTORNEYS_ON_STANDARD_FORM) {
                break;
            }
        }

        if (count($this->lpa->document->primaryAttorneys) == 1) {
            $this->drawingTargets[1] = array('primaryAttorney-1-pf');
        }

        // Section 4
        $i = 0;

        foreach ($this->sortAttorneys('replacementAttorneys') as $replacementAttorney) {
            if ($replacementAttorney instanceof TrustCorporation) {
                $this->dataForForm['replacement-attorney-' . $i . '-is-trust-corporation'] = self::CHECK_BOX_ON;
                $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-name-last'] = (string)$replacementAttorney->name;
            } else {
                $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-name-title'] = $replacementAttorney->name->title;
                $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-name-first'] = $replacementAttorney->name->first;
                $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-name-last'] = $replacementAttorney->name->last;

                $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-dob-date-day'] = $replacementAttorney->dob->date->format('d');
                $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-dob-date-month'] = $replacementAttorney->dob->date->format('m');
                $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-dob-date-year'] = $replacementAttorney->dob->date->format('Y');
            }

            $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-address-address1'] = $replacementAttorney->address->address1;
            $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-address-address2'] = $replacementAttorney->address->address2;
            $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-address-address3'] = $replacementAttorney->address->address3;
            $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-address-postcode'] = $replacementAttorney->address->postcode;

            $this->dataForForm['lpa-document-replacementAttorneys-' . $i . '-email-address'] = ($replacementAttorney->email instanceof EmailAddress ? "\n" . $replacementAttorney->email->address : null);

            if (++$i == self::MAX_REPLACEMENT_ATTORNEYS_ON_STANDARD_FORM) {
                break;
            }
        }

        $noOfReplacementAttorneys = count($this->lpa->document->replacementAttorneys);

        if ($noOfReplacementAttorneys == 0) {
            $this->drawingTargets[4] = array('replacementAttorney-0-pf', 'replacementAttorney-1-pf');
        } elseif ($noOfReplacementAttorneys == 1) {
            $this->drawingTargets[4] = array('replacementAttorney-1-pf');
        }

        //  When attroney can make decisions (Section 5)
        if ($this->lpa->document->primaryAttorneyDecisions instanceof PrimaryAttorneyDecisions) {
            if ($this->lpa->document->primaryAttorneyDecisions->when == PrimaryAttorneyDecisions::LPA_DECISION_WHEN_NOW) {
                $this->dataForForm['when-attorneys-may-make-decisions'] = 'when-lpa-registered';
            } elseif ($this->lpa->document->primaryAttorneyDecisions->when == PrimaryAttorneyDecisions::LPA_DECISION_WHEN_NO_CAPACITY) {
                $this->dataForForm['when-attorneys-may-make-decisions'] = 'when-donor-lost-mental-capacity';
            }
        }

        // Attorney/Replacement signature (Section 11)
        $allAttorneys = array_merge($this->lpa->document->primaryAttorneys, $this->lpa->document->replacementAttorneys);
        $attorneyIndex = 0;

        foreach ($allAttorneys as $attorney) {
            if ($attorney instanceof TrustCorporation) {
                continue;
            }

            $this->dataForForm['signature-attorney-' . $attorneyIndex . '-name-title'] = $attorney->name->title;
            $this->dataForForm['signature-attorney-' . $attorneyIndex . '-name-first'] = $attorney->name->first;
            $this->dataForForm['signature-attorney-' . $attorneyIndex . '-name-last'] = $attorney->name->last;

            if (++$attorneyIndex == self::MAX_ATTORNEY_SIGNATURE_PAGES_ON_STANDARD_FORM) {
                break;
            }
        }

        $numberOfHumanAttorneys = $attorneyIndex;

        switch ($numberOfHumanAttorneys) {
            case 3:
                $this->drawingTargets[14] = array('attorney-signature-pf');
                break;
            case 2:
                $this->drawingTargets[13] = array('attorney-signature-pf');
                $this->drawingTargets[14] = array('attorney-signature-pf');
                break;
            case 1:
                $this->drawingTargets[12] = array('attorney-signature-pf');
                $this->drawingTargets[13] = array('attorney-signature-pf');
                $this->drawingTargets[14] = array('attorney-signature-pf');
                break;
            case 0:
                $this->drawingTargets[11] = array('attorney-signature-pf');
                $this->drawingTargets[12] = array('attorney-signature-pf');
                $this->drawingTargets[13] = array('attorney-signature-pf');
                $this->drawingTargets[14] = array('attorney-signature-pf');
                break;
        }

        // Section 12
        if ($this->lpa->document->whoIsRegistering == 'donor') {
            $this->drawingTargets[16] = array('applicant-0-pf', 'applicant-1-pf', 'applicant-2-pf', 'applicant-3-pf');
        } elseif (is_array($this->lpa->document->whoIsRegistering)) {
            switch (count($this->lpa->document->whoIsRegistering)) {
                case 3:
                    $this->drawingTargets[16] = array('applicant-3-pf');
                    break;
                case 2:
                    $this->drawingTargets[16] = array('applicant-2-pf', 'applicant-3-pf');
                    break;
                case 1:
                    $this->drawingTargets[16] = array('applicant-1-pf', 'applicant-2-pf', 'applicant-3-pf');
                    break;
            }
        }

        $this->dataForForm['footer-instrument-right'] = $this->config['footer']['lp1f']['instrument'];
        $this->dataForForm['footer-registration-right'] = $this->config['footer']['lp1f']['registration'];

        return $this->dataForForm;
    }
}
