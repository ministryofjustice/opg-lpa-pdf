<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Attorneys\TrustCorporation;

trait AttorneysTrait
{
    /**
     * Get an array of all of the attorneys (primary and replacement) for this LPA
     *
     * @return array
     */
    public function getAllAttorneys()
    {
        return array_merge($this->lpa->document->primaryAttorneys, $this->lpa->document->replacementAttorneys);
    }

    /**
     * Get any trust attorney from the LPA details
     *
     * @return  TrustCorporation|null
     */
    protected function getTrustAttorney()
    {
        //  If no attorneys were passed into this function then get all of them from the LPA
        if (empty($attorneys)) {
            $attorneys = $this->getAllAttorneys();
        }

        foreach ($attorneys as $attorney) {
            if ($attorney instanceof TrustCorporation) {
                return $attorney;
            }
        }

        return null;
    }

    /**
     * Check if there is a trust attorney for this LPA
     *
     * @return bool
     */
    protected function hasTrustAttorney()
    {
        return ($this->getTrustAttorney() instanceof TrustCorporation);
    }

    /**
     * Sort the attorney array so that the trust is first (if there is one)
     *
     * @param $attorneyType
     * @return array
     */
    public function sortAttorneys($attorneyType)
    {
        //  Get the attorneys for the specified type
        $attorneys = $this->lpa->document->$attorneyType;

        //  If there is more than one attorney sort them
        if (is_array($attorneys) && count($attorneys) > 1) {
            $sortedAttorneys = [];
            $trustAttorney = null;

            foreach ($attorneys as $attorney) {
                if ($attorney instanceof TrustCorporation) {
                    $trustAttorney = $attorney;
                } else {
                    $sortedAttorneys[] = $attorney;
                }
            }

            if ($trustAttorney instanceof TrustCorporation) {
                array_unshift($sortedAttorneys, $trustAttorney);
            }

            $attorneys = $sortedAttorneys;
        }

        return $attorneys;
    }
}