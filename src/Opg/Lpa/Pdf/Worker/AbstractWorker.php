<?php

namespace Opg\Lpa\Pdf\Worker;

use Opg\Lpa\Pdf\Config\Config;
use Opg\Lpa\Pdf\Logger\Logger;
use Opg\Lpa\Pdf\Service\Forms\Lp1f;
use Opg\Lpa\Pdf\Service\Forms\Lp1h;
use Opg\Lpa\Pdf\Service\Forms\Lp3;
use Opg\Lpa\Pdf\Service\Forms\Lpa120;
use Opg\Lpa\Pdf\Worker\Response\AbstractResponse;
use Opg\Lpa\DataModel\Lpa\Document\Document;
use Opg\Lpa\DataModel\Lpa\Lpa;
use Exception;
use SplFileInfo;
use UnexpectedValueException;

abstract class AbstractWorker
{
    /**
     * Logger utility
     *
     * @var Logger
     */
    protected $logger;

    /**
     * AbstractWorker constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();

        //  Copy LPA PDF template files into ram disk if they are not found
        $assetsConfig = Config::getInstance()['service']['assets'];
        $templatePathOnDisk = $assetsConfig['template_path_on_ram_disk'];

        if (!\file_exists($templatePathOnDisk)) {
            $this->logger->info('Making template path on RAM disk', [
                'path' => $templatePathOnDisk,
            ]);

            mkdir($templatePathOnDisk, 0777, true);
        }

        //  TODO - does this need to execute every time?
        foreach (glob($assetsConfig['source_template_path'] . '/*.pdf') as $pdfSource) {
            $pathInfo = pathinfo($pdfSource);

            if (!file_exists($templatePathOnDisk . '/' . $pathInfo['basename'])) {
                $dest = $templatePathOnDisk . '/' . $pathInfo['basename'];

                $this->logger->info('Copying PDF source to RAM disk', [
                    'destination' => $dest,
                ]);

                copy($pdfSource, $dest);
            }
        }
    }

    /**
     * @param string $docId Unique ID representing this job/document.
     * @param string $type The type of PDF to generate.
     * @param string $lpaData JSON document representing the LPA document.
     * @throws Exception
     */
    public function run($docId, $type, $lpaData)
    {
        $lpaId = null;

        //  Initialise the log message params
        $message = 'PDF successfully generated';
        $isError = false;

        //  Define the data that will be used in the logging messages
        $loggingParams = [
            'docId' => $docId,
            'type'  => $type,
        ];

        try {
            if (is_array($lpaData) && isset($lpaData['id'])) {
                $lpaId = $lpaData['id'];
            } else {
                $lpaDecode = json_decode($lpaData);

                if (property_exists($lpaDecode, 'id')) {
                    $lpaId = $lpaDecode->id;
                } else {
                    throw new Exception('Missing field: id in JSON for docId: ' . $docId . ' This can be caused by incorrectly configured encryption keys.');
                }
            }

            //  Add the LPA ID to the logging parameters
            $loggingParams['lpaId'] = $lpaId;

            //  Create the LPA data model and validate it
            $lpa = new Lpa($lpaData);

            if ($lpa->validate()->hasErrors()) {
                throw new Exception('LPA failed validation');
            }

            //  Generate the required PDF
            if ($type == 'LP1' && $lpa->document->type == Document::LPA_TYPE_PF) {
                $pdf = new Lp1f($lpa);
            } elseif ($type == 'LP1' && $lpa->document->type == Document::LPA_TYPE_HW) {
                $pdf = new Lp1h($lpa);
            } elseif ($type == 'LP3') {
                $pdf = new Lp3($lpa);
            } elseif ($type == 'LPA120') {
                $pdf = new Lpa120($lpa);
            } else {
                throw new UnexpectedValueException('Invalid form type: ' . $type);
            }

            //  TODO - Check the return type here?
            $pdf->generate();

            //  Add the file path to the logging params
            $loggingParams['filePath'] = $pdf->getPdfFilePath();

            //  Save the generated file in the response
            $response = $this->getResponseObject($docId);
            $response->save(new SplFileInfo($pdf->getPdfFilePath()));

            $pdf->cleanup();
        } catch (Exception $e) {
            $isError = true;
            $message = 'PDF generation failed with exception: ' . $e->getMessage();
        }

        //  Pre-append the Doc ID to the message and log it
        $message = $docId . ': ' . $message;

        if ($isError) {
            $this->logger->err($message, $loggingParams);
        } else {
            $this->logger->info($message, $loggingParams);
        }

        echo $message . "\n";
    }

    /**
     * Return the object for handling the response
     *
     * @param $docId
     * @return AbstractResponse
     */
    abstract protected function getResponseObject($docId);
}
