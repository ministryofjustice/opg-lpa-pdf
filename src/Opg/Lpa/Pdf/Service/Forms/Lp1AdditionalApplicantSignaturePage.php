<?php
namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Lpa;
use Opg\Lpa\DataModel\Lpa\Document\Document;
use Opg\Lpa\Pdf\Config\Config;

class Lp1AdditionalApplicantSignaturePage extends AbstractForm
{
    /**
     * Duplicate Section 15 for additional applicant signatures
     * 
     * @param Lpa $lpa
     */
    public function __construct(Lpa $lpa)
    {
        parent::__construct($lpa);
    }
    
    public function generate()
    {
        $totalApplicantSignatures = count($this->lpa->document->whoIsRegistering);
        $totalAdditionalApplicantSignatures = $totalApplicantSignatures - Lp1::MAX_ATTORNEY_APPLICANTS_SIGNATURE_ON_STANDARD_FORM;
        $totalAdditionalApplicantSignaturePages = ceil($totalAdditionalApplicantSignatures/Lp1::MAX_ATTORNEY_APPLICANTS_SIGNATURE_ON_STANDARD_FORM);
        
        $totalMappedAdditionalApplicantSignaturePages = 0;
        for($i=0; $i<$totalAdditionalApplicantSignaturePages; $i++) {
            
            $filePath = $this->registerTempFile('AdditionalApplicantSignature');
            
            $additionalApplicantSignaturePage = PdfProcessor::getPdftkInstance($this->pdfTemplatePath."/LP1_AdditionalApplicantSignature.pdf");
            
            $formData = [];
            
            if($this->lpa->document->type == Document::LPA_TYPE_PF) {
                $formData['footer_registration_right'] = Config::getInstance()['footer']['lp1f']['registration'];
            }
            else {
                $formData['footer_registration_right'] = Config::getInstance()['footer']['lp1h']['registration'];
            }
            
            $additionalApplicantSignaturePage->fillForm($formData)
                ->flatten()
                ->saveAs($filePath);
        
        } // endfor
        
        return $this->interFileStack;
    } // function generate()
    
    public function __destruct()
    {
        
    }
} // class Lp1AdditionalApplicantSignaturePage