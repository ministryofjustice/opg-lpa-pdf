<?php

namespace Opg\Lpa\Pdf\Service\Forms;

class Cs2PrimaryAttorneyDecisions extends AbstractCs2
{
    /**
     * (non-PHPdoc)
     * @see \Opg\Lpa\Pdf\Service\Forms\AbstractForm::generate()
     */
    public function generate()
    {
        $this->logGenerationStatement();

        $content = $this->lpa->document->primaryAttorneyDecisions->howDetails;

        $formattedContentLength = strlen($this->flattenTextContent($content));

        $totalAdditionalPages = ceil($formattedContentLength / ((self::BOX_CHARS_PER_ROW + 2) * self::BOX_NO_OF_ROWS_CS2));

        for ($i = 0; $i < $totalAdditionalPages; $i++) {
            $filePath = $this->registerTempFile('CS2');

            //  Set the PDF form data
            $formData = [];
            $formData['cs2-is'] = 'decisions';
            $formData['cs2-content'] = $this->getFormattedContent($i, $content);
            $formData['cs2-donor-full-name'] = $this->lpa->document->donor->name->__toString();
            $formData['cs2-continued'] = ($i > 0 ? '(Continued)' : '');
            $formData['cs2-footer-right'] = $this->config['footer']['cs2'];

            $pdf = $this->getPdfObject(true);
            $pdf->fillForm($formData)
                ->flatten()
                ->saveAs($filePath);
        }

        return $this->interFileStack;
    }

    private function getFormattedContent($pageNo, $content)
    {
        $flattenContent = $this->flattenTextContent($content);

        $chunks = str_split($flattenContent, (self::BOX_CHARS_PER_ROW + 2) * self::BOX_NO_OF_ROWS_CS2);

        $formattedContent = null;

        if (isset($chunks[$pageNo])) {
            $formattedContent = "\r\n" . $chunks[$pageNo];
        }

        return $formattedContent;
    }
}