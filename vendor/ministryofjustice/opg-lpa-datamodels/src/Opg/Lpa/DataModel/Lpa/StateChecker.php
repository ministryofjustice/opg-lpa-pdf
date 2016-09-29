<?php
namespace Opg\Lpa\DataModel\Lpa;

use InvalidArgumentException;

use Opg\Lpa\DataModel\Lpa\Document\Document;
use Opg\Lpa\DataModel\Lpa\Document\Donor;
use Opg\Lpa\DataModel\Lpa\Document\Decisions\PrimaryAttorneyDecisions;
use Opg\Lpa\DataModel\Lpa\Document\Attorneys\AbstractAttorney;
use Opg\Lpa\DataModel\Lpa\Document\Decisions\AbstractDecisions;
use Opg\Lpa\DataModel\Lpa\Document\Decisions\ReplacementAttorneyDecisions;
use Opg\Lpa\DataModel\Lpa\Document\CertificateProvider;
use Opg\Lpa\DataModel\Lpa\Document\Correspondence;
use Opg\Lpa\DataModel\Lpa\Document\NotifiedPerson;
use Opg\Lpa\DataModel\Lpa\Payment\Payment;
use Opg\Lpa\DataModel\Lpa\Document\Attorneys\TrustCorporation;
use Opg\Lpa\DataModel\Lpa\Payment\Calculator;


/**
 * Provides a library of methods for checking the state of
 * an LPA object from a business domain perspective.
 *
 * Class StateChecker
 * @package Opg\Lpa\DataModel\Lpa
 */
class StateChecker {

    /**
     * LPA instance to apply checks to.
     *
     * @var LPA
     */
    protected $lpa;

    //---

    /**
     * @param Lpa $lpa LPA instance to apply checks to.
     */
    public function __construct(Lpa $lpa = null)
    {
        if ($lpa) {
            $this->setLpa($lpa);
        }
    }

    /**
     * Sets the LPA instance to apply checks to.
     *
     * @param Lpa $lpa
     */
    public function setLpa(Lpa $lpa)
    {
        $this->lpa = $lpa;
    }

    /**
     * Return the LPA.
     *
     * @return LPA
     */
    public function getLpa(){

        if( !($this->lpa instanceof Lpa) ){
            throw new InvalidArgumentException('No LPA has been set');
        }

        return $this->lpa;
    }

    //------------------------------------------------------------------------
    // For Generation Checks

    /**
     * Can a LP1 currently be generated.
     *
     * @return bool
     */
    public function canGenerateLP1(){
        return $this->isStateCreated();
    }

    /**
     * Can a LP3 currently be generated.
     *
     * @return bool
     */
    public function canGenerateLP3(){
        $lpa = $this->getLpa();
        return $this->isStateCompleted() && (count($lpa->document->peopleToNotify) > 0);
    }

    /**
     * Can a LPA120 currently be generated.
     *
     * @return bool
     */
    public function canGenerateLPA120(){

        if( !$this->isStateCompleted() ){
            return false;
        }

        //---

        $lpa = $this->getLpa();

        //---
        
        if( !($lpa->payment instanceof Payment) ){
            return false;
        }
        
        return $this->isEligibleForFeeReduction();

    } // function

    //------------------------------------------------------------------------
    // State Checks

    /**
     * Checks if the LPA has been started (from the perspective of the business)
     *
     * @return bool
     */
    public function isStateStarted(){
        $lpa = $this->getLpa();
        return is_int( $lpa->id );
    }

    /**
     * Checks if the LPA is Created (from the perspective of the business)
     *
     * @return bool
     */
    public function isStateCreated(){
        return $this->isStateStarted() && $this->lpaHasFinishedCreation();
    }

    /**
     * Checks if the LPA is Complete (from the perspective of the business)
     *
     * @return bool
     */
    public function isStateCompleted(){
        return $this->isStateCreated() && $this->paymentResolved();
    }

    //------------------------------------------------------------------------
    // Below are the functions copied from the front2 model.
    
    /**
     * Payment either paid online or offline, or no payment to be taken.
     * @return boolean
     */
    protected function paymentResolved()
    {
        if(!($this->lpa->payment instanceof Payment)) {
            return false;
        }
        
        if($this->lpa->payment->reducedFeeReceivesBenefits && $this->lpa->payment->reducedFeeAwardedDamages) {
            // has exemption
            return true;
        }
        elseif($this->lpa->payment->reducedFeeUniversalCredit) {
            // receive universal credit
            return true;
        }
        else {
            if($this->lpa->payment->method == Payment::PAYMENT_TYPE_CARD) {
                // pay by card
                if($this->lpa->payment->reference != null) {
                    // payment ref received from payment service provider
                    return true;
                }
                else {
                    // payment ref not received from payment service provider
                    return false;
                }
            }
            elseif($this->lpa->payment->method == Payment::PAYMENT_TYPE_CHEQUE) {
                // pay by cheque
                return true;
            }
            else {
                // must have a payment method if amount is greater than 0.
                return false;
            }
        }
    }
    
    /**
     * is the donor eligible for fee reduction due to having benefit, damage, income or universal credit.
     * 
     * @return boolean
     */
    public function isEligibleForFeeReduction()
    {
        $lpa = $this->getLpa();
        if(!($lpa->payment instanceof Payment)) {
            return false;
        }
        
        return ((($lpa->payment->reducedFeeReceivesBenefits) && ($lpa->payment->reducedFeeAwardedDamages)) 
                || ($lpa->payment->reducedFeeUniversalCredit)
                || ($lpa->payment->reducedFeeLowIncome));
    }

    protected function isWhoAreYouAnswered()
    {
        return ($this->lpaHasCorrespondent() && ($this->lpa->whoAreYouAnswered==true));
    }

    protected function lpaHasCorrespondent()
    {
        return ($this->lpaHasApplicant() && ($this->lpa->document->correspondent instanceof Correspondence));
    }

    protected function lpaHasApplicant()
    {
        return ($this->lpaHasFinishedCreation() &&
            ( ($this->lpa->document->whoIsRegistering == 'donor')
                ||
                ( is_array($this->lpa->document->whoIsRegistering)
                    &&
                    (count($this->lpa->document->whoIsRegistering)>0)
                )
            )
        );
    }

    /**
     * Lpa all required properties has value to qualify as an Instrument 
     * 
     * @return boolean
     */
    protected function lpaHasFinishedCreation()
    {
        return (
            $this->lpaHasCertificateProvider() &&
            (($this->lpa->document->instruction!==null)||($this->lpa->document->preference!==null)) &

            // Blocks if a second Primary attorney is added, post Replacement attorney.
            ( !$this->lpaHasMultiplePrimaryAttorneys() || $this->lpaHowPrimaryAttorneysMakeDecisionHasValue() ) &

            // Blocks if a second Replacement attorney is added, post Cert Provider.
            ( !$this->lpaHasReplacementAttorney() || !$this->lpaHasMultipleReplacementAttorneys() ||

                (
                    // Either we're good if this is answered; or
                    $this->lpaHowReplacementAttorneysMakeDecisionHasValue() ||

                    // If PA is JandS, and RA Steps in when first PA cannot.
                    ( $this->lpaReplacementAttorneyStepInWhenFirstPrimaryUnableAct() && $this->lpaPrimaryAttorneysMakeDecisionJointlyAndSeverally() )
                )

            ) &

            // Blocks if a second Primary attorney is added, and we have one or more Replacement attorney, post Cert Provider.
            ( !$this->lpaHasMultiplePrimaryAttorneys() || !$this->lpaHasReplacementAttorney() || !$this->lpaPrimaryAttorneysMakeDecisionJointlyAndSeverally() || $this->lpaWhenReplacementAttorneyStepInHasValue() )
        );
    }
    
    /**
     * LPA Instrument is created and created date is set
     * 
     * @return boolean
     */
    protected function lpaHasCreated()
    {
        return ($this->lpaHasFinishedCreation() && ($this->lpa->createdAt!==null));
    }
    
    protected function lpaHasPeopleToNotify($index = null)
    {
        if($index === null) {
            return ($this->lpaHasCertificateProvider()
                && ( count( $this->lpa->document->peopleToNotify ) > 0 ) );
        }
        else {
            return ($this->lpaHasCertificateProvider()
                && array_key_exists($index, $this->lpa->document->peopleToNotify)
                && ($this->lpa->document->peopleToNotify[$index] instanceof NotifiedPerson));
        }
    }

    protected function lpaHasCertificateProvider()
    {
        return ($this->lpaHasPrimaryAttorney() && ($this->lpa->document->certificateProvider instanceof CertificateProvider));
    }

    protected function lpaHowReplacementAttorneysMakeDecisionHasValue()
    {
        return ($this->lpaHasMultipleReplacementAttorneys()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && in_array($this->lpa->document->replacementAttorneyDecisions->how,
                [AbstractDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY,
                    AbstractDecisions::LPA_DECISION_HOW_JOINTLY,
                    AbstractDecisions::LPA_DECISION_HOW_DEPENDS]
            ));
    }

    protected function lpaReplacementAttorneysMakeDecisionJointlyAndSeverally()
    {
        return ($this->lpaHasMultipleReplacementAttorneys()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->replacementAttorneyDecisions->how == AbstractDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY));
    }

    protected function lpaReplacementAttorneysMakeDecisionJointly()
    {
        return ($this->lpaHasMultipleReplacementAttorneys()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->replacementAttorneyDecisions->how == AbstractDecisions::LPA_DECISION_HOW_JOINTLY));
    }

    protected function lpaReplacementAttorneysMakeDecisionDepends()
    {
        return ($this->lpaHasMultipleReplacementAttorneys()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->replacementAttorneyDecisions->how == AbstractDecisions::LPA_DECISION_HOW_DEPENDS));
    }

    protected function lpaWhenReplacementAttorneyStepInHasValue()
    {
        return ($this->lpaHasReplacementAttorney()
            && $this->lpaHasMultiplePrimaryAttorneys()
            && $this->lpaPrimaryAttorneysMakeDecisionJointlyAndSeverally()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && in_array($this->lpa->document->replacementAttorneyDecisions->when, [
                ReplacementAttorneyDecisions::LPA_DECISION_WHEN_FIRST,
                ReplacementAttorneyDecisions::LPA_DECISION_WHEN_LAST,
                ReplacementAttorneyDecisions::LPA_DECISION_WHEN_DEPENDS
            ]));
    }

    protected function lpaReplacementAttorneyStepInDepends()
    {
        return ($this->lpaHasReplacementAttorney()
            && $this->lpaHasMultiplePrimaryAttorneys()
            && $this->lpaPrimaryAttorneysMakeDecisionJointlyAndSeverally()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_DEPENDS));
    }

    protected function lpaReplacementAttorneyStepInWhenLastPrimaryUnableAct()
    {
        return ($this->lpaHasReplacementAttorney()
            && $this->lpaHasMultiplePrimaryAttorneys()
            && $this->lpaPrimaryAttorneysMakeDecisionJointlyAndSeverally()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_LAST));
    }

    protected function lpaReplacementAttorneyStepInWhenFirstPrimaryUnableAct()
    {
        return ($this->lpaHasReplacementAttorney()
            && $this->lpaHasMultiplePrimaryAttorneys()
            && $this->lpaPrimaryAttorneysMakeDecisionJointlyAndSeverally()
            && ($this->lpa->document->replacementAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->replacementAttorneyDecisions->when == ReplacementAttorneyDecisions::LPA_DECISION_WHEN_FIRST));
    }

    protected function lpaHasMultipleReplacementAttorneys()
    {
        return ($this->lpaHasReplacementAttorney() && (count($this->lpa->document->replacementAttorneys) > 1));
    }

    protected function lpaHasReplacementAttorney($index = null)
    {
        if($index === null) {
            return ($this->lpaHasPrimaryAttorney()
                && is_array($this->lpa->document->replacementAttorneys) && ( count( $this->lpa->document->replacementAttorneys ) > 0 ) );
        }
        else {
            return ($this->lpaHasPrimaryAttorney()
                && is_array($this->lpa->document->replacementAttorneys)
                && array_key_exists($index, $this->lpa->document->replacementAttorneys)
                && ($this->lpa->document->replacementAttorneys[$index] instanceof AbstractAttorney));
        }
    }

    protected function lpaHowPrimaryAttorneysMakeDecisionHasValue()
    {
        return ($this->lpaHasMultiplePrimaryAttorneys()
            && ($this->lpa->document->primaryAttorneyDecisions instanceof AbstractDecisions)
            && in_array($this->lpa->document->primaryAttorneyDecisions->how, [
                AbstractDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY,
                AbstractDecisions::LPA_DECISION_HOW_JOINTLY,
                AbstractDecisions::LPA_DECISION_HOW_DEPENDS
            ]));
    }

    protected function lpaPrimaryAttorneysMakeDecisionJointlyAndSeverally()
    {
        return ($this->lpaHasMultiplePrimaryAttorneys()
            && ($this->lpa->document->primaryAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->primaryAttorneyDecisions->how == AbstractDecisions::LPA_DECISION_HOW_JOINTLY_AND_SEVERALLY));
    }

    protected function lpaPrimaryAttorneysMakeDecisionJointly()
    {
        return ($this->lpaHasMultiplePrimaryAttorneys()
            && ($this->lpa->document->primaryAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->primaryAttorneyDecisions->how == AbstractDecisions::LPA_DECISION_HOW_JOINTLY));
    }

    protected function lpaPrimaryAttorneysMakeDecisionDepends()
    {
        return ($this->lpaHasMultiplePrimaryAttorneys()
            && ($this->lpa->document->primaryAttorneyDecisions instanceof AbstractDecisions)
            && ($this->lpa->document->primaryAttorneyDecisions->how == AbstractDecisions::LPA_DECISION_HOW_DEPENDS));
    }

    protected function lpaHasMultiplePrimaryAttorneys()
    {
        return ($this->lpaHasPrimaryAttorney() && (count($this->lpa->document->primaryAttorneys) > 1));
    }

    protected function lpaHasPrimaryAttorney($index = null)
    {
        if($index === null) {
            return (($this->lpaHasWhenLpaStarts() || $this->lpaHasLifeSustaining())
                && (count( $this->lpa->document->primaryAttorneys ) > 0 ) );
        }
        else {
            return (($this->lpaHasWhenLpaStarts() || $this->lpaHasLifeSustaining())
                && array_key_exists($index, $this->lpa->document->primaryAttorneys)
                && ($this->lpa->document->primaryAttorneys[$index] instanceof AbstractAttorney));
        }
    }
    
    protected function lpaHasTrustCorporation($whichGroup=null)
    {
        if($this->lpaHasWhenLpaStarts() || $this->lpaHasLifeSustaining()) {

            if($whichGroup == 'primary') {
                foreach($this->lpa->document->primaryAttorneys as $attorney) {
                    if($attorney instanceof TrustCorporation) {
                        return true;
                    }
                }
            }
            elseif($whichGroup == 'replacement') {
                foreach($this->lpa->document->replacementAttorneys as $attorney) {
                    if($attorney instanceof TrustCorporation) {
                        return true;
                    }
                }
            }
            else {
                foreach($this->lpa->document->primaryAttorneys as $attorney) {
                    if($attorney instanceof TrustCorporation) {
                        return true;
                    }
                }
                
                foreach($this->lpa->document->replacementAttorneys as $attorney) {
                    if($attorney instanceof TrustCorporation) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function lpaHasLifeSustaining()
    {
        return ($this->lpaHasDonor()
            && ($this->lpa->document->type == Document::LPA_TYPE_HW)
            && ($this->lpa->document->primaryAttorneyDecisions instanceof PrimaryAttorneyDecisions)
            && is_bool($this->lpa->document->primaryAttorneyDecisions->canSustainLife));
    }

    protected function lpaHasWhenLpaStarts()
    {
        return ($this->lpaHasDonor()
            && ($this->lpa->document->type == Document::LPA_TYPE_PF)
            && ($this->lpa->document->primaryAttorneyDecisions instanceof PrimaryAttorneyDecisions)
            && (in_array($this->lpa->document->primaryAttorneyDecisions->when, array(PrimaryAttorneyDecisions::LPA_DECISION_WHEN_NO_CAPACITY, PrimaryAttorneyDecisions::LPA_DECISION_WHEN_NOW))));
    }

    protected function lpaHasDonor()
    {
        return ($this->lpaHasType() && ($this->lpa->document->donor instanceof Donor));
    }

    protected function lpaHasType()
    {
        return $this->lpaHasDocument() && ($this->lpa->document->type != null);
    }

    protected function lpaHasDocument()
    {
        return $this->lpa->document instanceof Document;
    }

} // class
