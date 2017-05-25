<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Lpa;

class Cs4 extends AbstractCsForm
{
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
     * Company number of the trust
     *
     * @var
     */
    private $companyNumber;

    /**
     * Constructor
     *
     * @param Lpa $lpa
     * @param $companyNumber
     */
    public function __construct(Lpa $lpa, $companyNumber)
    {
        parent::__construct($lpa);

        $this->companyNumber = $companyNumber;
    }

    /**
     * Generate the correct number of continuation sheets
     *
     * @return array
     */
    public function generate()
    {
        $this->logStartMessage();

        $this->addFormData('cs4-trust-corporation-company-registration-number', $this->companyNumber)
             ->addFormData('cs4-footer-right', $this->getFooter());

        $filePath = $this->createContinuationSheetPdf();

        return $this->interFileStack;
    }
}