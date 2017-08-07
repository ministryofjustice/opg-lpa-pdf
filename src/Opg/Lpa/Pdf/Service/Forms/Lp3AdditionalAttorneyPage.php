<?php
namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Lpa;
use Opg\Lpa\Pdf\Config\Config;
use Opg\Lpa\Pdf\Service\PdftkInstance;

class Lp3AdditionalAttorneyPage extends AbstractForm
{
    /**
     * If there are more than 4 primary attorneys, duplicate page 3 - About the attorneys, to fit all attorneys in to the form.
     */
    public function __construct(Lpa $lpa)
    {
        parent::__construct($lpa);
    }

    public function generate()
    {
        $this->logGenerationStatement();

        $noOfAttorneys = count($this->lpa->document->primaryAttorneys);
        if($noOfAttorneys <= Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM) {
            return;
        }

        $additionalAttorneys = $noOfAttorneys - Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM;
        $additionalPages = ceil($additionalAttorneys/Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM);
        $populatedAttorneys = 0;

        $attorneys = $this->lpa->document->primaryAttorneys;
        sort($attorneys);
        for($i=0; $i<$additionalPages; $i++) {
            $filePath = $this->registerTempFile('AdditionalAttorneys');

            if($this->lpa->document->primaryAttorneyDecisions->how != null) {
                $this->pdfFormData['how-attorneys-act'] = $this->lpa->document->primaryAttorneyDecisions->how;
            }

            $additionalAttorneys = count($this->lpa->document->primaryAttorneys) - Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM;
            for($j=0; $j < Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM; $j++) {
                if($populatedAttorneys >= $additionalAttorneys) break;

                $attorneyIndex = Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM * ( 1 + $i ) + $j;
                if(is_string($attorneys[$attorneyIndex]->name)) {
                    $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-name-last']         = $attorneys[$attorneyIndex]->name;
                }
                else {
                    $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-name-title']        = $attorneys[$attorneyIndex]->name->title;
                    $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-name-first']        = $attorneys[$attorneyIndex]->name->first;
                    $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-name-last']         = $attorneys[$attorneyIndex]->name->last;
                }
                $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-address-address1']  = $attorneys[$attorneyIndex]->address->address1;
                $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-address-address2']  = $attorneys[$attorneyIndex]->address->address2;
                $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-address-address3']  = $attorneys[$attorneyIndex]->address->address3;
                $this->pdfFormData['lpa-document-primaryAttorneys-'.$j.'-address-postcode']  = $attorneys[$attorneyIndex]->address->postcode;

                if(++$populatedAttorneys == $additionalAttorneys) {
                    break;
                }
            }

            $this->pdfFormData['footer-right-page-three'] = Config::getInstance()['footer']['lp3'];

            $this->pdf = PdftkInstance::getInstance($this->pdfTemplatePath."/LP3_AdditionalAttorney.pdf");

            $this->pdf->fillForm($this->pdfFormData)
                      ->flatten()
                      ->saveAs($filePath);
        } //endfor

        if($additionalAttorneys % Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM) {
            $crossLineParams = array(array());
            for($k=Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM-$additionalAttorneys%Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM; $k>=1; $k--) {
                // draw on page 0.
                $crossLineParams[0][] = 'lp3-primaryAttorney-' . (Lp3::MAX_ATTORNEYS_ON_STANDARD_FORM-$k);
            }
            $this->drawCrossLines($filePath, $crossLineParams);
        }

        return $this->interFileStack;
    }

    public function __destruct()
    {
    }
}