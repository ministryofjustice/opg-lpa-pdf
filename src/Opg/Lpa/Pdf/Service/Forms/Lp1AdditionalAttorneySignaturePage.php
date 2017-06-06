<?php
namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Lpa;
use Opg\Lpa\DataModel\Lpa\Document\Attorneys\TrustCorporation;
use Opg\Lpa\Pdf\Config\Config;
use Opg\Lpa\DataModel\Lpa\Document\Document;
use Opg\Lpa\Pdf\Service\PdftkInstance;

class Lp1AdditionalAttorneySignaturePage extends AbstractForm
{
    /**
     * Duplicate Section 11 page for additional primary and replacement attorneys to sign
     *
     * @param Lpa $lpa
     */
    public function __construct(Lpa $lpa)
    {
        parent::__construct($lpa);
    }

    public function generate()
    {
        $this->logger->info('Generating Lp1 Additional Attorney Signature Page', [
            'lpaId' => $this->lpa->id
        ]);

        $allAttorneys = array_merge($this->lpa->document->primaryAttorneys, $this->lpa->document->replacementAttorneys);

        $skipped=0;
        foreach($allAttorneys as $attorney) {

            // skip trust corp
            if($attorney instanceof TrustCorporation) continue;

            // skip first 4 human attorneys
            $skipped++;
            if($skipped <= Lp1::MAX_ATTORNEY_SIGNATURE_PAGES_ON_STANDARD_FORM) continue;

            $filePath = $this->registerTempFile('AdditionalAttorneySignature');

            $lpaType = ($this->lpa->document->type == Document::LPA_TYPE_PF)?'lp1f':'lp1h';
            $attorneySignaturePage = PdftkInstance::getInstance($this->pdfTemplatePath. (($this->lpa->document->type == Document::LPA_TYPE_PF)?"/LP1F_AdditionalAttorneySignature.pdf":"/LP1H_AdditionalAttorneySignature.pdf"));
            $attorneySignaturePage->fillForm(array(
                    'signature-attorney-name-title' => $attorney->name->title,
                    'signature-attorney-name-first' => $attorney->name->first,
                    'signature-attorney-name-last'  => $attorney->name->last,
                    'footer-instrument-right-additional'    => Config::getInstance()['footer'][$lpaType]['instrument'],
            ))
            ->flatten()
            ->saveAs($filePath);

        } //endforeach

        return $this->interFileStack;
    }

    public function __destruct()
    {
    }
}