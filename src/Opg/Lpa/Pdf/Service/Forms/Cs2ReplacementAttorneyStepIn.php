<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Document\Decisions\PrimaryAttorneyDecisions;
use Opg\Lpa\DataModel\Lpa\Document\Decisions\ReplacementAttorneyDecisions;

class Cs2ReplacementAttorneyStepIn extends AbstractCs2
{
    /**
     * Generate the required continuation sheet(s)
     *
     * @return array
     */
    public function generate()
    {
        $this->contentType = Lp1::CONTENT_TYPE_REPLACEMENT_ATTORNEY_STEP_IN;

        //  Determine what content to use based on the contents of the LPA
        $replacementAttorneys = $this->lpa->document->replacementAttorneys;

        if (count($replacementAttorneys) > 0) {
            $multiplePrimaryAttorneys = (count($this->lpa->document->primaryAttorneys) > 1);
            $multipleReplacementAttorneys = (count($replacementAttorneys) > 1);

            $primaryAttorneysDecisions = $this->lpa->document->primaryAttorneyDecisions;
            $replacementAttorneyDecisions = $this->lpa->document->replacementAttorneyDecisions;

            //  If there are multiple replacement attorneys AND...
            //  1 - a single primary attorney, OR...
            //  2 - multiple primary attorneys that act jointly
            if ($multipleReplacementAttorneys && (!$multiplePrimaryAttorneys || $primaryAttorneysDecisions->how == PrimaryAttorneyDecisions::LPA_DECISION_HOW_JOINTLY)) {
                if ($replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY) {
                    $this->content = "Replacement attorneys are to act jointly and severally\r\n";
                } elseif ($replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_DEPENDS) {
                    $this->content = "Replacement attorneys are to act jointly for some decisions and jointly and severally for others, as below:\r\n" . $replacementAttorneyDecisions->howDetails . "\r\n";
                }
            } elseif ($multiplePrimaryAttorneys && $primaryAttorneysDecisions->how == PrimaryAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY) {
                //  If there are multiple primary attorneys AND they act jointly and severally
                //  Write the content as if there is one replacement attorney - if there are multiple attorneys the content will be changed
                if ($replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_LAST) {
                    $this->content = "Replacement attorney to step in only when none of the original attorneys can act\r\n";

                    if ($multipleReplacementAttorneys) {
                        //  There are multiple replacement attorneys so change the content slightly
                        $this->content = "Replacement attorneys to step in only when none of the original attorneys can act\r\n";

                        if ($replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY) {
                            $this->content .= "Replacement attorneys are to act jointly and severally\r\n";
                        } elseif ($replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_DEPENDS) {
                            $this->content .= "Replacement attorneys are to act joint for some decisions, joint and several for other decisions, as below:\r\n" . $replacementAttorneyDecisions->howDetails . "\r\n";
                        } elseif ($replacementAttorneyDecisions->how == ReplacementAttorneyDecisions::LPA_DECISION_HOW_JOINTLY) {
                            $this->content = '';
                        }
                    }
                } elseif ($replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_DEPENDS) {
                    $this->content = "How replacement attorneys will replace the original attorneys:\r\n" . $replacementAttorneyDecisions->whenDetails;

                    if ($multipleReplacementAttorneys) {
                        $this->content = "How replacement attorneys will replace the original attorneys:\r\n" . $replacementAttorneyDecisions->whenDetails;
                    }
                }
            }
        }

        return parent::generate();
    }
}