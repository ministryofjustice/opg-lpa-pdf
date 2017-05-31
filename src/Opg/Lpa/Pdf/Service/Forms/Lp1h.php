<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Decisions\PrimaryAttorneyDecisions;

class Lp1h extends Lp1
{
    /**
     * PDF filename
     *
     * @var string
     */
    protected $pdfFilename = 'LP1H.pdf';

    /**
     * PDF reference
     *
     * @var string
     */
    protected $pdfRef = 'lp1h';

    /**
     * String value that is added to the end of some data mapping key values
     *
     * @var string
     */
    protected $dataMappingKeySuffix = 'hw';

    /**
     * Set the data mappings for section 5 of the LPA document
     */
    protected function dataMappingSection5()
    {
        //  Section 5 - Life Sustaining treatment
        $primaryAttorneyDecisions = $this->lpa->document->primaryAttorneyDecisions;
        if ($primaryAttorneyDecisions instanceof PrimaryAttorneyDecisions) {
            $lifeSustainingAnswerStrikeThrough = ($primaryAttorneyDecisions->canSustainLife === true ? 'life-sustain-B' : 'life-sustain-A');
            $this->drawingTargets[5] = [$lifeSustainingAnswerStrikeThrough];
        }
    }
}