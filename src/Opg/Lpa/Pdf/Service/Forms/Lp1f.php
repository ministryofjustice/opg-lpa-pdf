<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Decisions\PrimaryAttorneyDecisions;

class Lp1f extends Lp1
{
    /**
     * PDF filename
     *
     * @var string
     */
    protected $pdfFilename = 'LP1F.pdf';

    /**
     * PDF reference
     *
     * @var string
     */
    protected $pdfRef = 'lp1f';

    /**
     * String value that is added to the end of some data mapping key values
     *
     * @var string
     */
    protected $dataMappingKeySuffix = 'pf';

    /**
     * Set the data mappings for section 5 of the LPA document
     */
    protected function dataMappingSection5()
    {
        //  Section 5 - When attorneys can make decisions
        $primaryAttorneyDecisions = $this->lpa->document->primaryAttorneyDecisions;
        if ($primaryAttorneyDecisions instanceof PrimaryAttorneyDecisions) {
            $this->pdfFormData['when-attorneys-may-make-decisions'] = ($primaryAttorneyDecisions->when == PrimaryAttorneyDecisions::LPA_DECISION_WHEN_NOW ? 'when-lpa-registered' : 'when-donor-lost-mental-capacity');
        }
    }

    /**
     * Generate additional pages depending on the LPA's composition
     */
    protected function generateAdditionalPages()
    {
        //  First generate the additional pages using the parent function
        parent::generateAdditionalPages();

        //  If required generate the continuation sheet 4
        if ($this->hasTrustAttorney()) {
            $continuationSheet4 = new Cs4($this->lpa);
            $generatedCs4 = $continuationSheet4->generate();
            $this->mergerIntermediateFilePaths($generatedCs4);
        }
    }
}