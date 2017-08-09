<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Common\Address;
use Opg\Lpa\DataModel\Common\EmailAddress;
use Opg\Lpa\DataModel\Common\LongName;
use Opg\Lpa\DataModel\Common\Name;
use Opg\Lpa\DataModel\Common\PhoneNumber;
use Opg\Lpa\DataModel\Lpa\Document\Correspondence;
use Opg\Lpa\DataModel\Lpa\Document\Document;
use Opg\Lpa\DataModel\Lpa\Lpa;
use mikehaertl\pdftk\Pdf;

class Lpa120 extends AbstractForm
{
    public function __construct(Lpa $lpa)
    {
        parent::__construct($lpa);

        //  Generate a file path with lpa id and timestamp;
        $this->generatedPdfFilePath = $this->getTmpFilePath('PDF-LPA120');

        $this->pdf = new Pdf($this->pdfTemplatePath . '/LPA120.pdf');
    }

    /**
     * Populate LPA data into PDF forms, generate pdf file and save into file path.
     *
     * @return $this
     */
    public function generate()
    {
        $this->logGenerationStatement();

        $lpa = $this->lpa;
        $lpaPayment = $lpa->payment;

        //  Check eligibility for exemption or remission.
        if(!$lpa->repeatCaseNumber
            && !$lpaPayment->reducedFeeLowIncome
            && !($lpaPayment->reducedFeeReceivesBenefits && $lpaPayment->reducedFeeAwardedDamages)
            && !$lpaPayment->reducedFeeUniversalCredit) {

            throw new \RuntimeException("LPA120 is not available for this LPA.");
        }

        $this->generatedPdfFilePath = $this->registerTempFile('LPA120');

        // populate forms
        $this->pdf->fillForm($this->dataMapping())
                  ->flatten()
                  ->saveAs($this->generatedPdfFilePath);

        $this->protectPdf();

        return $this;
    }

    /**
     * Get the data mapping for this document
     *
     * @return array
     * @throws \Exception
     */
    protected function dataMapping()
    {
        $lpa = $this->lpa;
        $lpaDocument = $lpa->document;
        $lpaPayment = $lpa->payment;

        $applicantType = 'other';
        $applicantTypeOther = null;
        $applicantPhoneNumber = null;

        //  The correspondent takes precedence over who is registering if specified
        if ($lpaDocument->correspondent instanceof Correspondence) {
            $applicant = $lpaDocument->correspondent;

            if ($applicant->who == Correspondence::WHO_DONOR) {
                $applicantType = 'donor';
            } else if ($applicant->who == Correspondence::WHO_ATTORNEY) {
                $applicantType = 'attorney';
            } else {
                $applicantTypeOther = 'Correspondent';
            }

            if ($applicant->phone instanceof PhoneNumber) {
                //  If the correspondent has a phone number then grab that value now
                $applicantPhoneNumber = $applicant->phone->number;
            }
        } else {
            //  Get applicant object
            if ($lpaDocument->whoIsRegistering == 'donor') {
                $applicant = $lpaDocument->donor;
                $applicantType = 'donor';
            } elseif (is_array($lpaDocument->whoIsRegistering)) {
                //  Get the first element in the whoIsRegistering array as the attorney applicant of the LPA
                foreach ($lpaDocument->whoIsRegistering as $attorneyId) {
                    $applicant = $lpaDocument->getPrimaryAttorneyById($attorneyId);
                    $applicantType = 'attorney';
                    break;
                }
            } else {
                throw new \Exception('When generating LPA120, applicant was found invalid');
            }
        }

        //  Get the applicant name details
        $applicantTitle = null;
        $applicantTitleOther = null;
        $applicantFirstName = null;
        $applicantLastName = $applicant->name;  //  Default the applicant last name here in case the value is a string for a company

        if ($applicant->name instanceof Name || $applicant->name instanceof LongName) {
            $applicantTitle = strtolower($applicant->name->title);

            //  If the applicant title is an other type then swap the values around
            if (!in_array($applicantTitle, ['mr','mrs','miss','ms'])) {
                $applicantTitleOther = $applicant->name->title; //  Use the original value here and not the lowercase version
                $applicantTitle = 'other';
            }

            $applicantFirstName = $applicant->name->first;
            $applicantLastName = $applicant->name->last;
        }

        $this->pdfFormData['donor-full-name'] = $this->fullName($lpaDocument->donor->name);
        $this->pdfFormData['donor-address'] = "\n" . (string) $lpaDocument->donor->address;
        $this->pdfFormData['lpa-type'] = ($lpaDocument->type == Document::LPA_TYPE_PF ? 'property-and-financial-affairs' : 'health-and-welfare');
        $this->pdfFormData['is-repeat-application'] = (is_null($lpa->repeatCaseNumber) ? null : self::CHECK_BOX_ON);
        $this->pdfFormData['case-number'] = $lpa->repeatCaseNumber;
        $this->pdfFormData['applicant-type'] = $applicantType;
        $this->pdfFormData['applicant-type-other'] = $applicantTypeOther;
        $this->pdfFormData['applicant-name-title'] = $applicantTitle;
        $this->pdfFormData['applicant-name-title-other'] = $applicantTitleOther;
        $this->pdfFormData['applicant-name-first'] = $applicantFirstName;
        $this->pdfFormData['applicant-name-last'] = $applicantLastName;
        $this->pdfFormData['applicant-address'] = "\n" . ($applicant->address instanceof Address ? (string) $applicant->address : '');
        $this->pdfFormData['applicant-phone-number'] = $applicantPhoneNumber;
        $this->pdfFormData['applicant-email-address'] = ($applicant->email instanceof EmailAddress ? (string) $applicant->email : null);
        $this->pdfFormData['receive-benefits'] = $this->getYesNoNullValueFromBoolean($lpaPayment->reducedFeeReceivesBenefits);
        $this->pdfFormData['damage-awarded'] = (is_null($lpaPayment->reducedFeeAwardedDamages) ? null : $this->getYesNoNullValueFromBoolean(!$lpaPayment->reducedFeeAwardedDamages));
        $this->pdfFormData['low-income'] = $this->getYesNoNullValueFromBoolean($lpaPayment->reducedFeeLowIncome);
        $this->pdfFormData['receive-universal-credit'] = $this->getYesNoNullValueFromBoolean($lpaPayment->reducedFeeUniversalCredit);

        return $this->pdfFormData;
    }

    /**
     * Simple function to return a yes/no string or null value
     *
     * @param $valueIn
     * @return null|string
     */
    private function getYesNoNullValueFromBoolean($valueIn)
    {
        if ($valueIn === true) {
            return 'yes';
        } elseif($valueIn === false) {
            return 'no';
        }

        return null;
    }
}
