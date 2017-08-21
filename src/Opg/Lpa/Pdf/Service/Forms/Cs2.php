<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\DataModel\Lpa\Lpa;

class Cs2 extends AbstractForm
{
    /**
     * Filename of the PDF template to use
     *
     * @var string|array
     */
    protected $pdfTemplateFile = 'LPC_Continuation_Sheet_2.pdf';

    private $contentType;
    private $content;

    /**
     * @param Lpa $lpa
     * @param string $contentType
     * @param string $content
     */
    public function __construct(Lpa $lpa, $contentType, $content)
    {
        parent::__construct($lpa);

        $this->contentType = $contentType;
        $this->content = $content;
    }

    /**
     * (non-PHPdoc)
     * @see \Opg\Lpa\Pdf\Service\Forms\AbstractForm::generate()
     */
    public function generate()
    {
        $this->logGenerationStatement();

        $cs2Continued = '';
        $formatedContentLength = strlen($this->flattenTextContent($this->content));
        if (($this->contentType == self::CONTENT_TYPE_ATTORNEY_DECISIONS) || ($this->contentType == self::CONTENT_TYPE_REPLACEMENT_ATTORNEY_STEP_IN)) {
            $totalAdditionalPages = ceil($formatedContentLength / ((self::BOX_CHARS_PER_ROW + 2) * self::BOX_NO_OF_ROWS_CS2));
        } else {
            $contentLengthOnStandardForm = (self::BOX_CHARS_PER_ROW + 2) * self::BOX_NO_OF_ROWS;
            $totalAdditionalPages = ceil(($formatedContentLength - $contentLengthOnStandardForm) / ((self::BOX_CHARS_PER_ROW + 2) * self::BOX_NO_OF_ROWS_CS2));
        }

        for ($i = 0; $i < $totalAdditionalPages; $i++) {
            $filePath = $this->registerTempFile('CS2');

            if (($this->contentType == self::CONTENT_TYPE_ATTORNEY_DECISIONS) || ($this->contentType == self::CONTENT_TYPE_REPLACEMENT_ATTORNEY_STEP_IN)) {
                $pageNo = $i;
            } else {
                $pageNo = $i + 1;
            }

            if (($i > 0) ||
                ($this->contentType == self::CONTENT_TYPE_PREFERENCES) ||
                ($this->contentType == self::CONTENT_TYPE_INSTRUCTIONS)) {
                $cs2Continued = '(Continued)';
            }

            //  Set the PDF form data
            $this->dataForForm['cs2-is'] = $this->contentType;
            $this->dataForForm['cs2-content'] = $this->getContentForBox($pageNo, $this->content, $this->contentType);
            $this->dataForForm['cs2-donor-full-name'] = $this->lpa->document->donor->name->__toString();
            $this->dataForForm['cs2-continued'] = $cs2Continued;
            $this->dataForForm['cs2-footer-right'] = $this->config['footer']['cs2'];

            $pdf = $this->getPdfObject(true);
            $pdf->fillForm($this->dataForForm)
                ->flatten()
                ->saveAs($filePath);
        }

        return $this->interFileStack;
    }
}
