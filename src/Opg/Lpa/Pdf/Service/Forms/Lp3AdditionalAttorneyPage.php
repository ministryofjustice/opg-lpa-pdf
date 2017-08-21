<?php

namespace Opg\Lpa\Pdf\Service\Forms;

class Lp3AdditionalAttorneyPage extends AbstractForm
{
    /**
     * Filename of the PDF template to use
     *
     * @var string|array
     */
    protected $pdfTemplateFile = 'LP3_AdditionalAttorney.pdf';

    public function generate()
    {
        $this->logGenerationStatement();

        $primaryAttorneys = $this->lpa->document->primaryAttorneys;

        //  Get the additional attorneys
        $additionalAttorneys = array_slice($primaryAttorneys, self::MAX_ATTORNEYS_ON_STANDARD_FORM);

        //  Loop through the additional attorneys (after the first 4) and add them into the additional sheets (4 per sheet)
        if (count($additionalAttorneys) > 0) {
            $dataForForm = [];
            $i = 0;

            foreach ($additionalAttorneys as $j => $additionalAttorney) {
                if (is_string($additionalAttorney->name)) {
                    $dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-last'] = $additionalAttorney->name;
                } else {
                    $dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-title'] = $additionalAttorney->name->title;
                    $dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-first'] = $additionalAttorney->name->first;
                    $dataForForm['lpa-document-primaryAttorneys-' . $i . '-name-last'] = $additionalAttorney->name->last;
                }

                $dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-address1'] = $additionalAttorney->address->address1;
                $dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-address2'] = $additionalAttorney->address->address2;
                $dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-address3'] = $additionalAttorney->address->address3;
                $dataForForm['lpa-document-primaryAttorneys-' . $i . '-address-postcode'] = $additionalAttorney->address->postcode;

                //  Iterate to the next space on the sheet
                $i++;

                //  If we've got too far, or this is the last additional attorney, output a page
                if ($i == self::MAX_ATTORNEYS_ON_STANDARD_FORM || ($j + 1 == count($additionalAttorneys))) {
                    //  Complete the remaining data
                    $dataForForm['how-attorneys-act'] = $this->lpa->document->primaryAttorneyDecisions->how;
                    $dataForForm['footer-right-page-three'] = $this->config['footer']['lp3'];

                    $filePath = $this->registerTempFile('AdditionalAttorneys');

                    $pdf = $this->getPdfObject(true);
                    $pdf->fillForm($dataForForm)
                        ->flatten()
                        ->saveAs($filePath);

                    //  If $i is less than the number of attorneys on a sheet then insert some strike through lines
                    $crossLineParams = [];

                    while ($i < self::MAX_ATTORNEYS_ON_STANDARD_FORM) {
                        $crossLineParams[] = 'lp3-primaryAttorney-' . $i;
                        $i++;
                    }

                    if (!empty($crossLineParams)) {
                        $this->drawCrossLines($filePath, [$crossLineParams]);
                    }

                    //  Reset the loop data for the next iteration
                    $dataForForm = [];
                    $i = 0;
                }
            }
        }

        return $this->interFileStack;
    }
}
