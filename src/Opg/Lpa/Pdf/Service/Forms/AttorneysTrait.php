<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Attorneys\TrustCorporation;

trait AttorneysTrait
{
    /**
     * if there is a trust corp, make it the first item in the attorneys array.
     *
     * @param string $attorneyType - 'primaryAttorneys'|'replacementAttorneys'
     * @return array of primaryAttorneys or replacementAttorneys
     */
    public function sortAttorneys($attorneyType)
    {
        $attorneys = $this->lpa->document->$attorneyType;

        //  If there is more than one attorney sort them
        if (count($attorneys) > 1 && $this->hasTrustCorporation($attorneys)) {
            $sortedAttorneys = [];

            foreach ($attorneys as $attorney) {
                if ($attorney instanceof TrustCorporation) {
                    $trustAttorney = $attorney;
                } else {
                    $sortedAttorneys[] = $attorney;
                }
            }

            array_unshift($sortedAttorneys, $trustAttorney);
            $attorneys = $sortedAttorneys;
        }

        return $attorneys;
    }

    /**
     * check if there is a trust corp in the whole LPA or in primary attorneys or replacement attorneys.
     */
    protected function hasTrustCorporation($attorneys = null)
    {
        if (null == $attorneys) {
            foreach ($this->lpa->document->primaryAttorneys as $attorney) {
                if ($attorney instanceof TrustCorporation) {
                    return true;
                }
            }

            foreach ($this->lpa->document->replacementAttorneys as $attorney) {
                if ($attorney instanceof TrustCorporation) {
                    return true;
                }
            }
        } else {
            foreach ($attorneys as $attorney) {
                if ($attorney instanceof TrustCorporation) {
                    return true;
                }
            }
        }

        return false;
    }
}