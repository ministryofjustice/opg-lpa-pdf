<?php

namespace Opg\Lpa\Pdf\Service\Forms;

use Opg\Lpa\Pdf\Config\Config;
use Opg\Lpa\Pdf\Logger\Logger;
use Opg\Lpa\Pdf\Service\PdftkInstance;

abstract class AbstractCsForm extends AbstractForm
{
    /**
     * Short ref for this continuation sheet
     *
     * @var
     */
    protected $csRef;

    /**
     * Filename of the PDF document to use
     *
     * @var
     */
    protected $pdfTemplateFilename;

    /**
     * Array container to hold the data to insert into the PDF form
     *
     * @var array
     */
    private $formData = [];

    protected function logStartMessage()
    {
        $classShortName = basename(str_replace('\\', '/', get_class($this)));

        Logger::getInstance()->info('Generating ' . $classShortName, [
            'lpaId' => $this->lpa->id
        ]);
    }

    /**
     * Empty any existing form data
     */
    protected function clearFormData()
    {
        $this->formData = [];
    }

    /**
     * Chainable function to add data to the array container
     *
     * @param $dataRef
     * @param $dataValue
     * @return $this
     */
    protected function addFormData($dataRef, $dataValue)
    {
        $this->formData[$dataRef] = $dataValue;

        return $this;
    }

    /**
     * Create, populate and flatten the PDF document using the data provided and the filename variables
     * Returns the full filepath so it can be used by the calling function if required
     *
     * @return string
     */
    protected function createContinuationSheetPdf()
    {
        $filePath = $this->registerTempFile(strtoupper($this->csRef));

        $pdfFile = PdftkInstance::getInstance($this->pdfTemplatePath . '/' . $this->pdfTemplateFilename);

        $pdfFile->fillForm($this->formData)
                ->flatten()
                ->saveAs($filePath);

        return $filePath;
    }

    protected function getFooter()
    {
        return Config::getInstance()['footer'][$this->csRef];
    }

    /**
     * Destruct stubbed out
     */
    public function __destruct()
    {
    }
}
