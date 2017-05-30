<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Attorneys\TrustCorporation;

class Cs4 extends AbstractCsForm
{
    use AttorneysTrait;

    /**
     * Short ref for this continuation sheet
     *
     * @var
     */
    protected $csRef = 'cs4';

    /**
     * Filename of the PDF document to use
     *
     * @var
     */
    protected $pdfTemplateFilename = 'LPC_Continuation_Sheet_4.pdf';

    /**
     * Generate the required continuation sheet(s)
     *
     * @return array
     */
    public function generate()
    {
        $this->logStartMessage();

        //  Get the company number from the trust in the LPA
        $trustAttorney = $this->getTrustAttorney();

        if (!$trustAttorney instanceof TrustCorporation) {
            new \RuntimeException('Trust attorney must be set to generate continuation sheet 4');
        }

        $this->addFormData('cs4-trust-corporation-company-registration-number', $trustAttorney->number)
             ->addFormData('cs4-footer-right', $this->getFooter());

        $filePath = $this->createContinuationSheetPdf();

        return $this->interFileStack;
    }
}