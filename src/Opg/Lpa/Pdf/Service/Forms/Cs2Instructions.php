<?php

namespace Opg\Lpa\Pdf\Service\Forms;

class Cs2Instructions extends AbstractCs2
{
    /**
     * Generate the required continuation sheet(s)
     *
     * @return array
     */
    public function generate()
    {
        $this->contentType = Lp1::CONTENT_TYPE_INSTRUCTIONS;
        $this->content = $this->lpa->document->instruction;

        //  TODO - Refactor to bring any logic into here?

        return parent::generate();
    }
}