<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Attorneys\TrustCorporation;
use Opg\Lpa\DataModel\Lpa\Document\Correspondence;
use Opg\Lpa\DataModel\Lpa\Document\Decisions\PrimaryAttorneyDecisions;
use Opg\Lpa\DataModel\Lpa\Document\Decisions\ReplacementAttorneyDecisions;
use Opg\Lpa\DataModel\Lpa\Formatter;
use Opg\Lpa\DataModel\Lpa\Payment\Payment;
use Opg\Lpa\DataModel\Lpa\Elements\EmailAddress;
use Opg\Lpa\DataModel\Lpa\Elements\Name;
use Opg\Lpa\DataModel\Lpa\Elements\PhoneNumber;
use Opg\Lpa\DataModel\Lpa\Lpa;
use Opg\Lpa\DataModel\Lpa\StateChecker;
use Opg\Lpa\Pdf\Config\Config;
use Opg\Lpa\Pdf\Logger\Logger;
use Opg\Lpa\Pdf\Service\PdftkInstance;
use mikehaertl\pdftk\Pdf;

use Zend\Barcode\Barcode;

abstract class Lp1 extends AbstractForm
{
    use AttorneysTrait;

    const BOX_CHARS_PER_ROW = 84;
    const BOX_NO_OF_ROWS = 6;

    const MAX_ATTORNEYS_ON_STANDARD_FORM = 4;
    const MAX_REPLACEMENT_ATTORNEYS_ON_STANDARD_FORM = 2;
    const MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM = 4;

    const MAX_ATTORNEY_SIGNATURE_PAGES_ON_STANDARD_FORM = 4;
    const MAX_ATTORNEY_APPLICANTS_ON_STANDARD_FORM = 4;
    const MAX_ATTORNEY_APPLICANTS_SIGNATURE_ON_STANDARD_FORM = 4;

    /**
     * PDFTK pdf object
     *
     * @var
     */
    protected $pdf;

    /**
     *  There or not the registration section of teh LPA is complete.
     *
     * @var bool
     */
    private $registrationIsComplete;

    /**
     * Store cross line strokes parameters.
     * The array index is the page number of pdf document,
     * and value is array of cross line param keys.
     *
     * @var array
     */
    protected $drawingTargets = [];

    /**
     * PDF filename
     * Set in the child classes
     *
     * @var string
     */
    protected $pdfFilename;

    /**
     * PDF reference
     * Set in the child classes
     *
     * @var string
     */
    protected $pdfRef;

    /**
     * String value that is added to the end of some data mapping key values
     * Set in the child classes
     *
     * @var string
     */
    protected $dataMappingKeySuffix;

    /**
     * Lp1 constructor
     *
     * @param Lpa $lpa
     */
    public function __construct(Lpa $lpa)
    {
        parent::__construct($lpa);

        $this->pdf = PdftkInstance::getInstance($this->pdfTemplatePath . '/' . $this->pdfFilename);

        $stateChecker = new StateChecker($lpa);
        $this->registrationIsComplete = $stateChecker->isStateCompleted();

        //  Generate a file path with lpa id and timestamp;
        $this->generatedPdfFilePath = $this->getTmpFilePath('PDF-' . strtoupper($this->pdfRef));
    }

    /**
     * Populate LPA data into PDF forms, generate pdf file.
     *
     * @return $this
     */
    public function generate()
    {
        Logger::getInstance()->info('Generating Lp1', [
            'lpaId' => $this->lpa->id
        ]);

        $this->generateStandardForm();
        $this->generateAdditionalPages();
        $this->generateAdditionalAttorneySignaturePages();
        $this->generateCoversheets();
        $this->mergePdfs();
        $this->protectPdf();

        return $this;
    }

    /**
     * Populate LP1F/H base template PDF and generate as temporary pdf for merging additional pages if needed to.
     */
    protected function generateStandardForm()
    {
        Logger::getInstance()->info('Generating Standard Form', [
            'lpaId' => $this->lpa->id
        ]);

        // register a random generated temp file path, and store it $interFileStack.
        $filePath = $this->registerTempFile('LP1');

        //  Set the data mappings - this will populate the required data in the pdfFormData class variable
        $this->dataMappingSection1Donor();
        $this->dataMappingSection2PrimaryAttorneys();
        $this->dataMappingSection3AttorneyDecisions();
        $this->dataMappingSection4ReplacementAttorneys();
        $this->dataMappingSection5();
        $this->dataMappingSection6PeopleToNotify();
        $this->dataMappingSection7PreferencesInstructions();
        $this->dataMappingSection9DonorSignature();
        $this->dataMappingSection10CertificateProvider();
        $this->dataMappingSection11AttorneysSignaturePages();
        $this->dataMappingSection12Applicant();
        $this->dataMappingSection13Correspondent();
        $this->dataMappingSection14Payment();
        $this->dataMappingSection15Signatures();
        $this->dataMappingFooter();

        // populate form data and generate pdf
        $this->pdf->fillForm($this->pdfFormData)
                  ->flatten()
                  ->saveAs($filePath);

        // If registration is complete add the tracking barcode.
        if ($this->registrationIsComplete) {
            $this->addLpaIdBarcode($filePath);
        }

        // draw cross lines if there's any blank slot
        if (!empty($this->drawingTargets)) {
            $this->drawCrossLines($filePath, $this->drawingTargets);
        }
    }

    /**
     * Set the data mappings for section 1 of the LPA document
     */
    private function dataMappingSection1Donor()
    {
        //  Section 1 - Donor details
        $donor = $this->lpa->document->donor;

        $this->pdfFormData['lpa-id'] = Formatter::id($this->lpa->id);
        $this->pdfFormData['lpa-document-donor-name-title'] = $donor->name->title;
        $this->pdfFormData['lpa-document-donor-name-first'] = $donor->name->first;
        $this->pdfFormData['lpa-document-donor-name-last'] = $donor->name->last;
        $this->pdfFormData['lpa-document-donor-otherNames'] = $donor->otherNames;
        $this->pdfFormData['lpa-document-donor-dob-date-day'] =  $donor->dob->date->format('d');
        $this->pdfFormData['lpa-document-donor-dob-date-month'] = $donor->dob->date->format('m');
        $this->pdfFormData['lpa-document-donor-dob-date-year'] = $donor->dob->date->format('Y');
        $this->pdfFormData['lpa-document-donor-address-address1']= $donor->address->address1;
        $this->pdfFormData['lpa-document-donor-address-address2']= $donor->address->address2;
        $this->pdfFormData['lpa-document-donor-address-address3']= $donor->address->address3;
        $this->pdfFormData['lpa-document-donor-address-postcode']= $donor->address->postcode;
        $this->pdfFormData['lpa-document-donor-email-address']= ($donor->email instanceof EmailAddress ? $donor->email->address : null);
    }

    /**
     * Set the data mappings for section 2 of the LPA document
     */
    private function dataMappingSection2PrimaryAttorneys()
    {
        //  Section 2 - Primary attorneys details
        $i = 0;
        $primaryAttorneys = $this->sortAttorneys('primaryAttorneys');

        foreach ($primaryAttorneys as $primaryAttorney) {
            if ($primaryAttorney instanceof TrustCorporation) {
                // $i should always be 0
                $this->pdfFormData['attorney-' . $i . '-is-trust-corporation'] = self::CHECK_BOX_ON;
                $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-name-last'] = (string)$primaryAttorney->name;
            } else {
                $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-name-title'] = $primaryAttorney->name->title;
                $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-name-first'] = $primaryAttorney->name->first;
                $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-name-last'] = $primaryAttorney->name->last;

                $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-dob-date-day'] = $primaryAttorney->dob->date->format('d');
                $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-dob-date-month'] = $primaryAttorney->dob->date->format('m');
                $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-dob-date-year'] = $primaryAttorney->dob->date->format('Y');
            }

            $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-address-address1'] = $primaryAttorney->address->address1;
            $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-address-address2'] = $primaryAttorney->address->address2;
            $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-address-address3'] = $primaryAttorney->address->address3;
            $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-address-postcode'] = $primaryAttorney->address->postcode;

            $this->pdfFormData['lpa-document-primaryAttorneys-' . $i . '-email-address'] = ($primaryAttorney->email instanceof EmailAddress ? "\n" . $primaryAttorney->email->address : null);

            if (++$i == self::MAX_ATTORNEYS_ON_STANDARD_FORM) {
                break;
            }
        }

        $noOfPrimaryAttorneys = count($primaryAttorneys);

        if ($noOfPrimaryAttorneys == 1) {
            $this->drawingTargets[1] = ['primaryAttorney-1-' . $this->dataMappingKeySuffix];
            $this->drawingTargets[2] = ['primaryAttorney-2', 'primaryAttorney-3'];
        } elseif ($noOfPrimaryAttorneys == 2) {
            $this->drawingTargets[2] = ['primaryAttorney-2', 'primaryAttorney-3'];
        } elseif ($noOfPrimaryAttorneys == 3) {
            $this->drawingTargets[2] = ['primaryAttorney-3'];
        } elseif ($noOfPrimaryAttorneys > 4) {
            $this->pdfFormData['has-more-than-4-attorneys'] = self::CHECK_BOX_ON;
        }
    }

    /**
     * Set the data mappings for section 3 of the LPA document
     */
    private function dataMappingSection3AttorneyDecisions()
    {
        //  Section 3 - Attorney decisions
        if (count($this->lpa->document->primaryAttorneys) == 1) {
            $this->pdfFormData['how-attorneys-act'] = 'only-one-attorney-appointed';
        } elseif ($this->lpa->document->primaryAttorneyDecisions instanceof PrimaryAttorneyDecisions) {
            $this->pdfFormData['how-attorneys-act'] = $this->lpa->document->primaryAttorneyDecisions->how;
        }
    }

    /**
     * Set the data mappings for section 4 of the LPA document
     */
    private function dataMappingSection4ReplacementAttorneys()
    {
        //  Section 4 - Replacement attorneys details
        $i = 0;
        $replacementAttorneys = $this->sortAttorneys('replacementAttorneys');

        foreach ($replacementAttorneys as $replacementAttorney) {
            if ($replacementAttorney instanceof TrustCorporation) {
                $this->pdfFormData['replacement-attorney-' . $i . '-is-trust-corporation'] = self::CHECK_BOX_ON;
                $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-name-last'] = (string)$replacementAttorney->name;
            } else {
                $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-name-title'] = $replacementAttorney->name->title;
                $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-name-first'] = $replacementAttorney->name->first;
                $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-name-last'] = $replacementAttorney->name->last;

                $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-dob-date-day'] = $replacementAttorney->dob->date->format('d');
                $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-dob-date-month'] = $replacementAttorney->dob->date->format('m');
                $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-dob-date-year'] = $replacementAttorney->dob->date->format('Y');
            }

            $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-address-address1'] = $replacementAttorney->address->address1;
            $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-address-address2'] = $replacementAttorney->address->address2;
            $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-address-address3'] = $replacementAttorney->address->address3;
            $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-address-postcode'] = $replacementAttorney->address->postcode;

            $this->pdfFormData['lpa-document-replacementAttorneys-' . $i . '-email-address'] = ($replacementAttorney->email instanceof EmailAddress ? "\n" . $replacementAttorney->email->address : null);

            if (++$i == self::MAX_REPLACEMENT_ATTORNEYS_ON_STANDARD_FORM) {
                break;
            }
        }

        $noOfReplacementAttorneys = count($replacementAttorneys);

        if ($noOfReplacementAttorneys == 0) {
            $this->drawingTargets[4] = ['replacementAttorney-0-' . $this->dataMappingKeySuffix, 'replacementAttorney-1-' . $this->dataMappingKeySuffix];
        } elseif ($noOfReplacementAttorneys == 1) {
            $this->drawingTargets[4] = ['replacementAttorney-1-' . $this->dataMappingKeySuffix];
        } elseif ($noOfReplacementAttorneys > 2) {
            $this->pdfFormData['has-more-than-2-replacement-attorneys'] = self::CHECK_BOX_ON;
        }

        // checkbox for replacement decisions are not taking the default arrangement.
        $noOfPrimaryAttorneys = count($this->lpa->document->primaryAttorneys);
        $replacementAttorneyDecisions = $this->lpa->document->replacementAttorneyDecisions;
        $changeHowReplacementAttorneysStepIn = false;

        if ($noOfPrimaryAttorneys == 1) {
            if ($noOfReplacementAttorneys > 1
                && ($replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY
                    || $replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_DEPENDS)) {

                $changeHowReplacementAttorneysStepIn = true;
            }
        } elseif ($noOfPrimaryAttorneys > 1) {
            switch ($this->lpa->document->primaryAttorneyDecisions->how) {
                case PrimaryAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY:
                    if ($noOfReplacementAttorneys == 1) {
                        $changeHowReplacementAttorneysStepIn = in_array($replacementAttorneyDecisions->when, [
                            ReplacementAttorneyDecisions::LPA_DECISION_WHEN_LAST,
                            ReplacementAttorneyDecisions::LPA_DECISION_WHEN_DEPENDS
                        ]);
                    } elseif ($noOfReplacementAttorneys > 1) {
                        if ($replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_DEPENDS) {
                            $changeHowReplacementAttorneysStepIn = true;
                        } elseif ($replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_LAST) {
                            $changeHowReplacementAttorneysStepIn = in_array($replacementAttorneyDecisions->how, [
                                ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY,
                                ReplacementAttorneyDecisions::LPA_DECISION_HOW_DEPENDS
                            ]);
                        }
                    }

                    break;
                case PrimaryAttorneyDecisions::LPA_DECISION_HOW_JOINTLY:
                    if ($noOfReplacementAttorneys > 1) {
                        $changeHowReplacementAttorneysStepIn = in_array($replacementAttorneyDecisions->how, [
                            ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY,
                            ReplacementAttorneyDecisions::LPA_DECISION_HOW_DEPENDS
                        ]);
                    }
                    break;
            }
        }

        //  If required render the checkbox
        if ($changeHowReplacementAttorneysStepIn) {
            $this->pdfFormData['change-how-replacement-attorneys-step-in'] = self::CHECK_BOX_ON;
        }
    }

    /**
     * Set the data mappings for section 5 of the LPA document
     * This is different for each LPA so the logic is in the child classes
     */
    abstract protected function dataMappingSection5();

    /**
     * Set the data mappings for section 6 of the LPA document
     */
    private function dataMappingSection6PeopleToNotify()
    {
        //  Section 6 - People to notify
        $i = 0;

        $peopleToNotify = $this->lpa->document->peopleToNotify;

        foreach ($peopleToNotify as $personToNotify) {
            $this->pdfFormData['lpa-document-peopleToNotify-' . $i . '-name-title'] = $personToNotify->name->title;
            $this->pdfFormData['lpa-document-peopleToNotify-' . $i . '-name-first'] = $personToNotify->name->first;
            $this->pdfFormData['lpa-document-peopleToNotify-' . $i . '-name-last'] = $personToNotify->name->last;

            $this->pdfFormData['lpa-document-peopleToNotify-' . $i . '-address-address1'] = $personToNotify->address->address1;
            $this->pdfFormData['lpa-document-peopleToNotify-' . $i . '-address-address2'] = $personToNotify->address->address2;
            $this->pdfFormData['lpa-document-peopleToNotify-' . $i . '-address-address3'] = $personToNotify->address->address3;
            $this->pdfFormData['lpa-document-peopleToNotify-' . $i . '-address-postcode'] = $personToNotify->address->postcode;

            if (++$i == self::MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM) {
                break;
            }
        }

        $noOfPeopleToNotify = count($peopleToNotify);

        if ($noOfPeopleToNotify > self::MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM) {
            $this->pdfFormData['has-more-than-4-notified-people'] = self::CHECK_BOX_ON;
        } elseif ($noOfPeopleToNotify < self::MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM) {
            $this->drawingTargets[6] = [];

            for ($i = self::MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM - $noOfPeopleToNotify; $i > 0; $i--) {
                $this->drawingTargets[6][] = 'people-to-notify-' . (self::MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM - $i);
            }
        }
    }

    /**
     * Set the data mappings for section 7 of the LPA document
     */
    private function dataMappingSection7PreferencesInstructions()
    {
        //  Section 7 - Preference and instructions
        $preferences = $this->lpa->document->preference;
        $instructions = $this->lpa->document->instruction;
        $this->drawingTargets[7] = [];

        if (!empty($preferences)) {
            if (!$this->canFitIntoTextBox($preferences)) {
                $this->pdfFormData['has-more-preferences'] = self::CHECK_BOX_ON;
            }

            $this->pdfFormData['lpa-document-preference'] = $this->getContentForBox(0, $preferences, self::CONTENT_TYPE_PREFERENCES);
        } else {
            $this->drawingTargets[7][] = 'preference';
        }

        if (!empty($instructions)) {
            if (!$this->canFitIntoTextBox($instructions)) {
                $this->pdfFormData['has-more-instructions'] = self::CHECK_BOX_ON;
            }

            $this->pdfFormData['lpa-document-instruction'] = $this->getContentForBox(0, $instructions, self::CONTENT_TYPE_INSTRUCTIONS);
        } else {
            $this->drawingTargets[7][] = 'instruction';
        }
    }

    /**
     * Set the data mappings for section 9 of the LPA document
     */
    private function dataMappingSection9DonorSignature()
    {
        //  Section 9 - Donor signature page
        if ($this->lpa->document->donor->canSign === false) {
            $this->pdfFormData['see_continuation_sheet_3'] = 'see continuation sheet 3';
        }
    }

    /**
     * Set the data mappings for section 10 of the LPA document
     */
    private function dataMappingSection10CertificateProvider()
    {
        //  Section 10 - Populate certificate provider page
        $certificateProvider = $this->lpa->document->certificateProvider;

        $this->pdfFormData['lpa-document-certificateProvider-name-title'] = $certificateProvider->name->title;
        $this->pdfFormData['lpa-document-certificateProvider-name-first'] = $certificateProvider->name->first;
        $this->pdfFormData['lpa-document-certificateProvider-name-last'] = $certificateProvider->name->last;

        $this->pdfFormData['lpa-document-certificateProvider-address-address1'] = $certificateProvider->address->address1;
        $this->pdfFormData['lpa-document-certificateProvider-address-address2'] = $certificateProvider->address->address2;
        $this->pdfFormData['lpa-document-certificateProvider-address-address3'] = $certificateProvider->address->address3;
        $this->pdfFormData['lpa-document-certificateProvider-address-postcode'] = $certificateProvider->address->postcode;
    }

    /**
     * Set the data mappings for section 11 of the LPA document
     */
    private function dataMappingSection11AttorneysSignaturePages()
    {
        //  Section 11 - Attorney/replacement signature page
        $allAttorneys = $this->getAllAttorneys();
        $attorneyIndex = 0;

        foreach ($allAttorneys as $attorney) {
            if ($attorney instanceof TrustCorporation) {
                continue;
            }

            $this->pdfFormData['signature-attorney-' . $attorneyIndex . '-name-title'] = $attorney->name->title;
            $this->pdfFormData['signature-attorney-' . $attorneyIndex . '-name-first'] = $attorney->name->first;
            $this->pdfFormData['signature-attorney-' . $attorneyIndex . '-name-last'] = $attorney->name->last;

            if (++$attorneyIndex == self::MAX_ATTORNEY_SIGNATURE_PAGES_ON_STANDARD_FORM) {
                break;
            }
        }

        $numberOfHumanAttorneys = $attorneyIndex;
        $attorneySignatureLabel = 'attorney-signature-' . $this->dataMappingKeySuffix;

        switch ($numberOfHumanAttorneys) {
            case 3:
                $this->drawingTargets[14] = [$attorneySignatureLabel];
                break;
            case 2:
                $this->drawingTargets[13] = [$attorneySignatureLabel];
                $this->drawingTargets[14] = [$attorneySignatureLabel];
                break;
            case 1:
                $this->drawingTargets[12] = [$attorneySignatureLabel];
                $this->drawingTargets[13] = [$attorneySignatureLabel];
                $this->drawingTargets[14] = [$attorneySignatureLabel];
                break;
            case 0:
                $this->drawingTargets[11] = [$attorneySignatureLabel];
                $this->drawingTargets[12] = [$attorneySignatureLabel];
                $this->drawingTargets[13] = [$attorneySignatureLabel];
                $this->drawingTargets[14] = [$attorneySignatureLabel];
                break;
        }
    }

    /**
     * Set the data mappings for section 12 of the LPA document
     */
    private function dataMappingSection12Applicant()
    {
        //  Section 12 - Applicant
        if ($this->lpa->document->whoIsRegistering == 'donor') {
            $this->pdfFormData['who-is-applicant'] = 'donor';

            $this->drawingTargets[16] = [
                'applicant-0-' . $this->dataMappingKeySuffix,
                'applicant-1-' . $this->dataMappingKeySuffix,
                'applicant-2-' . $this->dataMappingKeySuffix,
                'applicant-3-' . $this->dataMappingKeySuffix
            ];
        } elseif (is_array($this->lpa->document->whoIsRegistering)) {
            $this->pdfFormData['who-is-applicant'] = 'attorney';
            $i = 0;

            foreach ($this->lpa->document->whoIsRegistering as $attorneyId) {
                $attorney = $this->lpa->document->getPrimaryAttorneyById($attorneyId);

                if ($attorney instanceof TrustCorporation) {
                    $this->pdfFormData['applicant-' . $i . '-name-last']      = $attorney->name;
                } else {
                    $this->pdfFormData['applicant-' . $i . '-name-title']     = $attorney->name->title;
                    $this->pdfFormData['applicant-' . $i . '-name-first']     = $attorney->name->first;
                    $this->pdfFormData['applicant-' . $i . '-name-last']      = $attorney->name->last;
                    $this->pdfFormData['applicant-' . $i . '-dob-date-day']   = $attorney->dob->date->format('d');
                    $this->pdfFormData['applicant-' . $i . '-dob-date-month'] = $attorney->dob->date->format('m');
                    $this->pdfFormData['applicant-' . $i . '-dob-date-year']  = $attorney->dob->date->format('Y');
                }

                if (++$i == self::MAX_ATTORNEY_APPLICANTS_ON_STANDARD_FORM) {
                    break;
                }
            }

            switch ($i) {
                case 3:
                    $this->drawingTargets[16] = [
                        'applicant-3-' . $this->dataMappingKeySuffix
                    ];
                    break;
                case 2:
                    $this->drawingTargets[16] = [
                        'applicant-2-' . $this->dataMappingKeySuffix,
                        'applicant-3-' . $this->dataMappingKeySuffix
                    ];
                    break;
                case 1:
                    $this->drawingTargets[16] = [
                        'applicant-1-' . $this->dataMappingKeySuffix,
                        'applicant-2-' . $this->dataMappingKeySuffix,
                        'applicant-3-' . $this->dataMappingKeySuffix
                    ];
                    break;
            }
        }
    }

    /**
     * Set the data mappings for section 13 of the LPA document
     */
    private function dataMappingSection13Correspondent()
    {
        //  Section 13 - Correspondent
        $correspondent = $this->lpa->document->correspondent;

        if ($correspondent instanceof Correspondence) {
            switch ($correspondent->who) {
                case Correspondence::WHO_DONOR:
                    $this->pdfFormData['who-is-correspondent'] = 'donor';

                    if ($correspondent->contactDetailsEnteredManually === true) {
                        $this->pdfFormData['lpa-document-correspondent-name-title'] = $correspondent->name->title;
                        $this->pdfFormData['lpa-document-correspondent-name-first'] = $correspondent->name->first;
                        $this->pdfFormData['lpa-document-correspondent-name-last'] = $correspondent->name->last;
                        $this->pdfFormData['lpa-document-correspondent-address-address1'] = $correspondent->address->address1;
                        $this->pdfFormData['lpa-document-correspondent-address-address2'] = $correspondent->address->address2;
                        $this->pdfFormData['lpa-document-correspondent-address-address3'] = $correspondent->address->address3;
                        $this->pdfFormData['lpa-document-correspondent-address-postcode'] = $correspondent->address->postcode;
                    } else {
                        $this->drawingTargets[17] = ['correspondent-empty-name-address'];
                    }

                    break;
                case Correspondence::WHO_ATTORNEY:
                    $isAddressCrossedOut = true;

                    $this->pdfFormData['who-is-correspondent'] = 'attorney';
                    if ($correspondent->name instanceof Name) {
                        $this->pdfFormData['lpa-document-correspondent-name-title'] = $correspondent->name->title;
                        $this->pdfFormData['lpa-document-correspondent-name-first'] = $correspondent->name->first;
                        $this->pdfFormData['lpa-document-correspondent-name-last'] = $correspondent->name->last;

                        if ($correspondent->contactDetailsEnteredManually === true) {
                            $this->pdfFormData['lpa-document-correspondent-address-address1'] = $correspondent->address->address1;
                            $this->pdfFormData['lpa-document-correspondent-address-address2'] = $correspondent->address->address2;
                            $this->pdfFormData['lpa-document-correspondent-address-address3'] = $correspondent->address->address3;
                            $this->pdfFormData['lpa-document-correspondent-address-postcode'] = $correspondent->address->postcode;
                            $isAddressCrossedOut = false;
                        }
                    }

                    if ($isAddressCrossedOut) {
                        $this->drawingTargets[17] = ['correspondent-empty-address'];
                    }

                    $this->pdfFormData['lpa-document-correspondent-company'] = $correspondent->company;

                    break;
                case Correspondence::WHO_OTHER:
                    $this->pdfFormData['who-is-correspondent'] = 'other';
                    $this->pdfFormData['lpa-document-correspondent-name-title'] = $correspondent->name->title;
                    $this->pdfFormData['lpa-document-correspondent-name-first'] = $correspondent->name->first;
                    $this->pdfFormData['lpa-document-correspondent-name-last'] = $correspondent->name->last;
                    $this->pdfFormData['lpa-document-correspondent-company'] = $correspondent->company;

                    $this->pdfFormData['lpa-document-correspondent-address-address1'] = $correspondent->address->address1;
                    $this->pdfFormData['lpa-document-correspondent-address-address2'] = $correspondent->address->address2;
                    $this->pdfFormData['lpa-document-correspondent-address-address3'] = $correspondent->address->address3;
                    $this->pdfFormData['lpa-document-correspondent-address-postcode'] = $correspondent->address->postcode;
                    break;
            }

            //  Correspondence preference
            if ($correspondent->contactByPost === true) {
                $this->pdfFormData['correspondent-contact-by-post'] = self::CHECK_BOX_ON;
            }

            if ($correspondent->phone instanceof PhoneNumber) {
                $this->pdfFormData['correspondent-contact-by-phone'] = self::CHECK_BOX_ON;
                $this->pdfFormData['lpa-document-correspondent-phone-number'] = str_replace(" ", "", $correspondent->phone->number);
            }

            if ($correspondent->email instanceof EmailAddress) {
                $this->pdfFormData['correspondent-contact-by-email'] = self::CHECK_BOX_ON;
                $this->pdfFormData['lpa-document-correspondent-email-address'] = $correspondent->email->address;
            }

            if ($correspondent->contactInWelsh === true) {
                $this->pdfFormData['correspondent-contact-in-welsh'] = self::CHECK_BOX_ON;
            }
        }
    }

    /**
     * Set the data mappings for section 14 of the LPA document
     */
    private function dataMappingSection14Payment()
    {
        //  Section 14 - Payment section
        //  Fee reduction, repeat application
        if ($this->lpa->repeatCaseNumber !== null) {
            $this->pdfFormData['is-repeat-application'] = self::CHECK_BOX_ON;
            $this->pdfFormData['repeat-application-case-number'] = $this->lpa->repeatCaseNumber;
        }

        $payment = $this->lpa->payment;

        if ($payment instanceof Payment) {
            // payment method
            if ($payment->method) {
                $this->pdfFormData['pay-by'] = $payment->method;
            }

            if ($payment->method == Payment::PAYMENT_TYPE_CARD) {
                $this->pdfFormData['lpa-payment-phone-number'] = "NOT REQUIRED.";
            }

            // apply to pay reduced fee
            if (($payment->reducedFeeReceivesBenefits && $payment->reducedFeeAwardedDamages)
                || $payment->reducedFeeLowIncome
                || $payment->reducedFeeUniversalCredit) {

                $this->pdfFormData['apply-for-fee-reduction'] = self::CHECK_BOX_ON;
            }

            // Online payment details
            if ($payment->reference !== null) {
                $this->pdfFormData['lpa-payment-reference'] = $payment->reference;
                $this->pdfFormData['lpa-payment-amount'] = '£' . sprintf('%.2f', $payment->amount);
                $this->pdfFormData['lpa-payment-date-day'] = $payment->date->format('d');
                $this->pdfFormData['lpa-payment-date-month'] = $payment->date->format('m');
                $this->pdfFormData['lpa-payment-date-year'] = $payment->date->format('Y');
            }
        }
    }

    /**
     * Set the data mappings for section 15 of the LPA document
     */
    private function dataMappingSection15Signatures()
    {
        //  Section 15 - Signature page
        $whoIsRegistering = $this->lpa->document->whoIsRegistering;

        if ($whoIsRegistering == 'donor') {
            $this->drawingTargets[19] = [
                'applicant-signature-1',
                'applicant-signature-2',
                'applicant-signature-3',
            ];
        } elseif (is_array($whoIsRegistering)) {
            //  Cross-out any unused boxes if we need less than 4.
            if (count($whoIsRegistering) < 4) {
                $this->drawingTargets[19] = [];

                for ($x = 3; $x >= count($whoIsRegistering); $x--) {
                    $this->drawingTargets[19][] = "applicant-signature-{$x}";
                }
            }
        }
    }

    /**
     * Set the data mappings for the footer of the LPA document
     */
    private function dataMappingFooter()
    {
        $footerConfig = Config::getInstance()['footer'];
        $this->pdfFormData['footer-instrument-right'] = $footerConfig[$this->pdfRef]['instrument'];
        $this->pdfFormData['footer-registration-right'] = $footerConfig[$this->pdfRef]['registration'];
    }

    /**
     * Add LPA barcode to PDF document
     *
     * @param $filePath
     */
    private function addLpaIdBarcode($filePath)
    {
        // Generate the barcode
        // Zero pad the ID, and prepend the 'A'.
        $formattedLpaId = 'A'.sprintf("%011d", $this->lpa->id);

        $renderer = Barcode::factory('code39', 'pdf', [
            'text' => $formattedLpaId,
            'drawText' => false,
            'factor' => 2,
            'barHeight' => 25,
        ], [
            'topOffset' => 789,
            'leftOffset' => 40,
        ]);

        $imageResource = $renderer->draw();

        $barcodeTmpFile = $this->getTmpFilePath('barcode');

        // Save to temporary file...
        $imageResource->save($barcodeTmpFile);

        // Merge the barcode into the page

        // Take a copy of the PDF to work with.
        $pdfWithBarcode = PdftkInstance::getInstance($filePath);

        // Pull out the page the barcode is appended to.
        $pdfWithBarcode->cat(19);

        // Add the barcode to the page.
        $pdfWithBarcode = new Pdf($pdfWithBarcode);
        $pdfWithBarcode->stamp($barcodeTmpFile);

        // Re-integrate the page into the full PDF.
        $pdf = new Pdf();

        $pdf->addFile($filePath, 'A');
        $pdf->addFile($pdfWithBarcode, 'B');

        // Swap out page 19 for the one with the barcode.
        $pdf->cat(1, 18, 'A');
        $pdf->cat(1, null, 'B');
        $pdf->cat(20, 'end', 'A');

        $pdf->flatten()->saveAs($filePath);

        // Cleanup
        // Remove tmp barcode file.
        unlink($barcodeTmpFile);
    }

    /**
     * Generate additional pages depending on the LPA's composition
     */
    protected function generateAdditionalPages()
    {
        Logger::getInstance()->info('Generating Additional Pages', [
            'lpaId' => $this->lpa->id
        ]);

        //  If appropriate generate the Cs1 continuation sheet
        if (count($this->lpa->document->primaryAttorneys) > self::MAX_ATTORNEYS_ON_STANDARD_FORM
            || count($this->lpa->document->replacementAttorneys) > self::MAX_REPLACEMENT_ATTORNEYS_ON_STANDARD_FORM
            || count($this->lpa->document->peopleToNotify) > self::MAX_PEOPLE_TO_NOTIFY_ON_STANDARD_FORM) {

            $continuationSheet1 = new Cs1($this->lpa);
            $generatedCs1 = $continuationSheet1->generate();
            $this->mergerIntermediateFilePaths($generatedCs1);
        }

        //  If appropriate generate the Cs2 continuation sheet if how attorneys make decisions depends on a special arrangement
        if ($this->lpa->document->primaryAttorneyDecisions->how == PrimaryAttorneyDecisions::LPA_DECISION_HOW_DEPENDS) {
            $continuationSheetAttorneyDecisions = new Cs2AttorneyDecisions($this->lpa);
            $generatedCs2 = $continuationSheetAttorneyDecisions->generate();
            $this->mergerIntermediateFilePaths($generatedCs2);
        }

        //  If appropriate generate the Cs2 continuation sheet for when replacement attorneys should step in
        $createRepAttorneyStepInContinuationSheet = false;

        $replacementAttorneys = $this->lpa->document->replacementAttorneys;

        if (count($replacementAttorneys) > 0) {
            $multiplePrimaryAttorneys = (count($this->lpa->document->primaryAttorneys) > 1);
            $multipleReplacementAttorneys = (count($replacementAttorneys) > 1);

            $primaryAttorneysDecisions = $this->lpa->document->primaryAttorneyDecisions;
            $replacementAttorneyDecisions = $this->lpa->document->replacementAttorneyDecisions;

            if ($multipleReplacementAttorneys && (!$multiplePrimaryAttorneys || $primaryAttorneysDecisions->how == PrimaryAttorneyDecisions::LPA_DECISION_HOW_JOINTLY)) {
                $createRepAttorneyStepInContinuationSheet = in_array($replacementAttorneyDecisions->how, [ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY, ReplacementAttorneyDecisions::LPA_DECISION_HOW_DEPENDS]);
            } elseif ($multiplePrimaryAttorneys && $primaryAttorneysDecisions->how == PrimaryAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY) {
                if ($replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_LAST) {
                    $createRepAttorneyStepInContinuationSheet = !($multipleReplacementAttorneys && $replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY);
                } elseif ($replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_DEPENDS) {
                    $createRepAttorneyStepInContinuationSheet = true;
                }
            }
        }

        if ($createRepAttorneyStepInContinuationSheet) {
            $continuationSheetReplacementAttorneyStepIn = new Cs2ReplacementAttorneyStepIn($this->lpa);
            $generatedCs2 = $continuationSheetReplacementAttorneyStepIn->generate();
            $this->mergerIntermediateFilePaths($generatedCs2);
        }

        //  If appropriate generate the Cs2 preferences continuation sheet
        if (!$this->canFitIntoTextBox($this->lpa->document->preference)) {
            $continuationSheetPreferences = new Cs2Preferences($this->lpa);
            $generatedCs2 = $continuationSheetPreferences->generate();
            $this->mergerIntermediateFilePaths($generatedCs2);
        }

        //  If appropriate generate the Cs2 instructions continuation sheet
        if (!$this->canFitIntoTextBox($this->lpa->document->instruction)) {
            $continuationSheetInstructions = new Cs2Instructions($this->lpa);
            $generatedCs2 = $continuationSheetInstructions->generate();
            $this->mergerIntermediateFilePaths($generatedCs2);
        }

        //  If appropriate generate the Cs3 continuation sheet
        if ($this->lpa->document->donor->canSign === false) {
            $continuationSheet3 = new Cs3($this->lpa);
            $generatedCs3 = $continuationSheet3->generate();
            $this->mergerIntermediateFilePaths($generatedCs3);
        }

        $numOfApplicants = count($this->lpa->document->whoIsRegistering);

        // Section 12 - Applicants. If number of applicant is greater than 4, duplicate this page as many as needed in order to fit all applicants in.
        if (is_array($this->lpa->document->whoIsRegistering) && $numOfApplicants > self::MAX_ATTORNEY_APPLICANTS_ON_STANDARD_FORM) {
            $additionalApplicantPage = new Lp1AdditionalApplicantPage($this->lpa);
            $generatedAdditionalApplicantPages = $additionalApplicantPage->generate();
            $this->mergerIntermediateFilePaths($generatedAdditionalApplicantPages);
        }

        // Section 15 - additional applicants signature
        if (is_array($this->lpa->document->whoIsRegistering) && $numOfApplicants > self::MAX_ATTORNEY_APPLICANTS_SIGNATURE_ON_STANDARD_FORM) {
            $totalAdditionalApplicants = $numOfApplicants - self::MAX_ATTORNEY_APPLICANTS_SIGNATURE_ON_STANDARD_FORM;
            $totalAdditionalApplicantPages = ceil($totalAdditionalApplicants/self::MAX_ATTORNEY_APPLICANTS_SIGNATURE_ON_STANDARD_FORM);

            if ($totalAdditionalApplicantPages > 0) {
                $additionalApplicantSignaturePage = new Lp1AdditionalApplicantSignaturePage($this->lpa);
                $generatedAdditionalApplicantSignaturePages = $additionalApplicantSignaturePage->generate();
                $this->mergerIntermediateFilePaths($generatedAdditionalApplicantSignaturePages);
            }
        }
    }

    /**
     * Generate any additional attorney signature pages
     */
    private function generateAdditionalAttorneySignaturePages()
    {
        //  Get the number of total number of human attorneys
        $totalHumanAttorneys = count($this->getAllAttorneys());

        if ($this->hasTrustAttorney()) {
            $totalHumanAttorneys--;
        }

        if ($totalHumanAttorneys > self::MAX_ATTORNEYS_ON_STANDARD_FORM) {
            $additionalAttorneySignaturePage = new Lp1AdditionalAttorneySignaturePage($this->lpa);
            $generatedAdditionalAttorneySignaturePages = $additionalAttorneySignaturePage->generate();
            $this->mergerIntermediateFilePaths($generatedAdditionalAttorneySignaturePages);
        }
    }

    /**
     * Generate the coversheets for the LPA document
     */
    private function generateCoversheets()
    {
        Logger::getInstance()->info('Generating Coversheets', [
            'lpaId' => $this->lpa->id
        ]);

        if (!$this->registrationIsComplete) {
            $coversheetInstrument = new CoversheetInstrument($this->lpa);
            $coversheetInstrument = $coversheetInstrument->generate();
            $this->mergerIntermediateFilePaths($coversheetInstrument);
        } else {
            $coversheetRegistration = new CoversheetRegistration($this->lpa);
            $coversheetRegistration = $coversheetRegistration->generate();
            $this->mergerIntermediateFilePaths($coversheetRegistration);
        }
    }

    /**
     * Merge generated intermediate pdf files
     */
    private function mergePdfs()
    {
        $pdf = PdftkInstance::getInstance();
        $registrationPdf = PdftkInstance::getInstance();
        $fileTag = $lp1FileTag = 'B';

        if (isset($this->interFileStack['LP1']) && isset($this->interFileStack['Coversheet'])) {
            $pdf->addFile($this->interFileStack['Coversheet'], 'A');
            $pdf->addFile($this->interFileStack['LP1'], $lp1FileTag);

            //  Add the blank single page PDF incase we need to cat it around continuation sheets
            $pdf->addFile(Config::getInstance()['service']['assets']['source_template_path'] . '/blank.pdf', 'BLANK');

            $registrationPdf->addFile($this->interFileStack['Coversheet'], 'A');
            $registrationPdf->addFile($this->interFileStack['LP1'], $lp1FileTag);
        } else {
            throw new \UnexpectedValueException('LP1 pdf was not generated before merging pdf intermediate files');
        }

        //  Cover section
        //  add cover sheet
        $pdf->cat(1, 'end', 'A');

        //  Instrument section
        //  add page 1-15
        $pdf->cat(1, 15, $lp1FileTag);

        //  Section 11 - additional attorneys signature
        if (isset($this->interFileStack['AdditionalAttorneySignature'])) {
            foreach ($this->interFileStack['AdditionalAttorneySignature'] as $additionalAttorneySignature) {
                $fileTag = $this->nextTag($fileTag);
                $pdf->addFile($additionalAttorneySignature, $fileTag);

                // add an additional attorney signature page
                $pdf->cat(1, null, $fileTag);
            }
        }

        //  Continuation Sheet 1
        if (isset($this->interFileStack['CS1'])) {
            foreach ($this->interFileStack['CS1'] as $cs1) {
                $fileTag = $this->nextTag($fileTag);
                $pdf->addFile($cs1, $fileTag);

                // add a CS1 page with a leading blank page
                $pdf->cat(1, null, 'BLANK');
                $pdf->cat(1, null, $fileTag);
            }
        }

        //  Continuation Sheet 2
        if (isset($this->interFileStack['CS2'])) {
            foreach ($this->interFileStack['CS2'] as $cs2) {
                $fileTag = $this->nextTag($fileTag);
                $pdf->addFile($cs2, $fileTag);

                // add a CS2 page with a leading blank page
                $pdf->cat(1, null, 'BLANK');
                $pdf->cat(1, null, $fileTag);
            }
        }

        //  Continuation Sheet 3
        if (isset($this->interFileStack['CS3'])) {
            $fileTag = $this->nextTag($fileTag);
            $pdf->addFile($this->interFileStack['CS3'], $fileTag);

            // add a CS3 page with a leading blank page
            $pdf->cat(1, null, 'BLANK');
            $pdf->cat(1, null, $fileTag);
        }

        //  Continuation Sheet 4
        if (isset($this->interFileStack['CS4'])) {
            $fileTag = $this->nextTag($fileTag);
            $pdf->addFile($this->interFileStack['CS4'], $fileTag);

            // add a CS4 page with a leading blank page
            $pdf->cat(1, null, 'BLANK');
            $pdf->cat(1, null, $fileTag);
        }

        //  If any continuation sheets were added then insert a trailing blank page
        if (array_key_exists('CS1', $this->interFileStack)
            ||array_key_exists('CS2', $this->interFileStack)
            ||array_key_exists('CS3', $this->interFileStack)
            ||array_key_exists('CS4', $this->interFileStack)) {

            $pdf->cat(1, null, 'BLANK');
        }

        //  Registration section
        //  Use a different instance for the rest of the registration
        //  pages so that (if needed) we can apply a stamp to them.

        //  Add the registration coversheet.
        $registrationPdf->cat(16, null, $lp1FileTag);

        $registrationPdf->cat(17, null, $lp1FileTag);

        //  Section 12 additional applicants
        if (isset($this->interFileStack['AdditionalApplicant'])) {
            foreach ($this->interFileStack['AdditionalApplicant'] as $additionalApplicant) {
                $fileTag = $this->nextTag($fileTag);
                $registrationPdf->addFile($additionalApplicant, $fileTag);

                // add an additional applicant page
                $registrationPdf->cat(1, null, $fileTag);
            }
        }

        // add page 18, 19, 20
        $registrationPdf->cat(18, 20, $lp1FileTag);

        // Section 15 - additional applicants signature
        if (isset($this->interFileStack['AdditionalApplicantSignature'])) {
            foreach ($this->interFileStack['AdditionalApplicantSignature'] as $additionalApplicantSignature) {
                $fileTag = $this->nextTag($fileTag);
                $registrationPdf->addFile($additionalApplicantSignature, $fileTag);

                // add an additional applicant signature page
                $registrationPdf->cat(1, null, $fileTag);
            }
        }

        //  If the registration section of the LPA isn't complete, we add the warning stamp
        if (!$this->registrationIsComplete) {
            $registrationPdf = new Pdf($registrationPdf);
            $registrationPdf->stamp($this->pdfTemplatePath . '/RegistrationWatermark.pdf');
        }

        // Merge the registration section in...
        $fileTag = $this->nextTag($fileTag);
        $pdf->addFile($registrationPdf, $fileTag);
        $pdf->cat(1, 'end', $fileTag);

        $pdf->saveAs($this->generatedPdfFilePath);
    }
}