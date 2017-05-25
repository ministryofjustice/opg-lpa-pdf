<?php

namespace Opg\Lpa\Pdf\Service\Forms;

class Cs3 extends AbstractCsForm
{
    /**
     * Short ref for this continuation sheet
     *
     * @var
     */
    protected $csRef = 'cs3';

    /**
     * Filename of the PDF document to use
     *
     * @var
     */
    protected $pdfTemplateFilename = 'LPC_Continuation_Sheet_3.pdf';

    /**
     * Generate the correct number of continuation sheets
     *
     * @return array
     */
    public function generate()
    {
        $this->logStartMessage();

        $this->addFormData('cs3-donor-full-name', $this->fullName($this->lpa->document->donor->name))
             ->addFormData('cs3-footer-right', $this->getFooter());

        $filePath = $this->createContinuationSheetPdf();

        return $this->interFileStack;
    }
}