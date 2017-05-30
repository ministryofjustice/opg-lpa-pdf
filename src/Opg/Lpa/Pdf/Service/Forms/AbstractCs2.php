<?php

namespace Opg\Lpa\Pdf\Service\Forms;

abstract class AbstractCs2 extends AbstractCsForm
{
    const BOX_NO_OF_ROWS_CS2 = 14;

    /**
     * Short ref for this continuation sheet
     *
     * @var
     */
    protected $csRef = 'cs2';

    /**
     * Filename of the PDF document to use
     *
     * @var
     */
    protected $pdfTemplateFilename = 'LPC_Continuation_Sheet_2.pdf';

    /**
     * Content type to process
     *
     * @var
     */
    protected $contentType;

    /**
     * Content of the required field
     *
     * @var
     */
    protected $content;

    /**
     * Generate the required continuation sheet(s)
     *
     * @return array
     */
    public function generate()
    {
        $this->logStartMessage();

        $formattedContentLength = strlen($this->flattenTextContent($this->content));

        //  Determine if the content being rendered is the instructions or preferences
        //  If it is then use the continued prefix
        $isInstructionsOrPreferences = in_array($this->contentType, [self::CONTENT_TYPE_INSTRUCTIONS, self::CONTENT_TYPE_PREFERENCES]);
        $useContinuedPrefix = $isInstructionsOrPreferences;

        //  Work out how many additional pages will be required taking into account the content length on the standard form
        $contentLengthOnStandardForm = ($isInstructionsOrPreferences ? (Lp1::BOX_CHARS_PER_ROW + 2) * Lp1::BOX_NO_OF_ROWS : 0);
        $contentLengthPerPage = (Lp1::BOX_CHARS_PER_ROW + 2) * self::BOX_NO_OF_ROWS_CS2;
        $totalAdditionalPages = ceil(($formattedContentLength - $contentLengthOnStandardForm) / $contentLengthPerPage);

        for ($i = 0; $i < $totalAdditionalPages; $i++) {
            //  Determine the page number to use
            $pageNo = ($isInstructionsOrPreferences ? $i + 1 : $i);

            //  Add the form data
            $this->addFormData('cs2-is', $this->contentType)
                 ->addFormData('cs2-content', $this->getContentForBox($pageNo, $this->content, $this->contentType))
                 ->addFormData('cs2-donor-full-name', $this->fullName($this->lpa->document->donor->name))
                 ->addFormData('cs2-continued', ($useContinuedPrefix ? '(Continued)' : ''))
                 ->addFormData('cs2-footer-right', $this->getFooter());

            $filePath = $this->createContinuationSheetPdf();

            //  Set the continuation prefix flag in case we are going to loop again
            $useContinuedPrefix = true;
        }

        return $this->interFileStack;
    }
}