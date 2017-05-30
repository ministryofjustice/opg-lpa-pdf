<?php

namespace Opg\Lpa\Pdf\Service\Forms;

class Cs2AttorneyDecisions extends AbstractCs2
{
    /**
     * Generate the required continuation sheet(s)
     *
     * @return array
     */
    public function generate()
    {
        $this->contentType = Lp1::CONTENT_TYPE_ATTORNEY_DECISIONS;
        $this->content = $this->lpa->document->primaryAttorneyDecisions->howDetails;

        //  TODO - Refactor to bring any logic into here?

        return parent::generate();
    }
}