<?php

namespace Opg\Lpa\Pdf\Service\Forms;

class Cs2Preferences extends AbstractCs2
{
    /**
     * Generate the required continuation sheet(s)
     *
     * @return array
     */
    public function generate()
    {
        $this->contentType = Lp1::CONTENT_TYPE_PREFERENCES;
        $this->content = $this->lpa->document->preference;

        return parent::generate();
    }
}