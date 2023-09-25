<?php

namespace Wexample\SymfonyTesting\Traits;

use Jfcherng\Diff\DiffHelper;
use Wexample\SymfonyPdf\Service\Pdf\Page\Page;

trait ResourceFileTestCaseTrait
{
    public function getTestResourceFilePath(string $pathRelative): string
    {
        return self::getContainer()->get('kernel')
                ->getProjectDir()
            .'/tests/Resources/'
            .$pathRelative;
    }

    protected function assertPdfMatchModel(
        string $pdfFrom,
        string $pdfModel,
        bool $forceRecreate = false
    ): void {
        if ($forceRecreate && is_file($pdfFrom)) {
            $this->logWarn('Overriding debug resource '.$pdfModel);

            copy(
                $pdfFrom,
                $pdfModel
            );
        }

        $this->assertFilesComparisonExists($pdfFrom, $pdfModel);

        $this->assertMetadataSame(
            $pdfFrom,
            $pdfModel
        );

        $this->assertPdfTextSame(
            $pdfFrom,
            $pdfModel,
        );

        $this->assertBinaryFileContentSame(
            $pdfFrom,
            $pdfModel,
            'File match to model'
        );
    }

    private function assertFilesComparisonExists(string $fileFrom, string $fileModel) {
        $this->assertTrue(
            is_file($fileFrom),
            'Source file exists : ' . $fileFrom
        );

        $this->assertTrue(
            is_file($fileModel),
            'Model file exists : ' . $fileModel
        );
    }

    protected function assertMetadataSame(
        string $pdfA,
        string $pdfB
    ): void {
        $dataA = Page::parsePdfMetadata($pdfA);
        $dataB = Page::parsePdfMetadata($pdfB);

        $this->assertTrue(
            is_array($dataA) === is_array($dataB),
            'Both metadata exists, or not.'
        );

        $this->assertIsArray($dataA);
        $this->assertIsArray($dataB);

        if (is_array($dataA)) {
            $this->assertEmpty(
                array_diff(
                    $dataA,
                    $dataB,
                )
            );
        }
    }

    protected function assertPdfTextSame(
        string $fileA,
        string $fileB,
        string $message = 'see below'
    ): void {
        // Parse PDF file and build necessary objects.

        if ($result = DiffHelper::calculate(
            Page::redactRawText(
                Page::parsePdfText($fileA)
            ),
            Page::redactRawText(
                Page::parsePdfText($fileB)
            )
        )) {
            echo $result;
            $this->error('Pdf texts does not match : '.$message);
        }
    }

    protected function assertBinaryFileContentSame(
        string $fileA,
        string $fileB,
        string $message = null
    ): void {
        $this->log('Binary comparison of files');
        $this->logSecondary('File A : '.$fileA);
        $this->logSecondary('File B : '.$fileB);

        $fileABinary = $this->redactBinaryText(
            file_get_contents($fileA)
        );

        $fileBBinary = $this->redactBinaryText(
            file_get_contents($fileB)
        );

        $match = $fileABinary === $fileBBinary;

        if (!$match) {
            $this->debugWrite(
                $fileABinary,
                'binary-diff-a.txt'
            );
            $this->debugWrite(
                $fileBBinary,
                'binary-diff-b.txt'
            );
        }

        $this->assertTrue(
            $match,
            $message
        );
    }

    /**
     * Replace sanitized text that is relevant for changes comparison.
     */
    protected function redactBinaryText(
        string $content,
        string $replacement = 'XXXXXX'
    ): string {
        return trim($content);
    }
}
