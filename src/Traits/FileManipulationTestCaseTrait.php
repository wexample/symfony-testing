<?php

namespace Wexample\SymfonyTesting\Traits;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyHelpers\Helper\FileHelper;

trait FileManipulationTestCaseTrait
{
    public string $FILE_NAME_50Kb = '50Kb';

    public array $FILE_MIME_TYPE = [
        FileHelper::FILE_EXTENSION_PDF => 'application/pdf',
    ];

    public function getStorageDir($name = null): string
    {
        return self::getContainer()->get('kernel')->getProjectDir().
            '/var/'.($name ? $name.'/' : '');
    }

    public function getFileTestPath(
        $size = null,
        $format = FileHelper::FILE_EXTENSION_PDF
    ): string {
        $size = $size ?: $this->FILE_NAME_50Kb;

        return $this->getStorageDir()
            .'../src/Wex/BaseBundle/Resources/files/test'.$size.'.'.$format;
    }

    public function fileUploadPrepare(string $path): UploadedFile
    {
        $this->assertFileExists($path);
        $info = pathinfo($path);
        $infoOpen = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($infoOpen, $path);

        return new UploadedFile(
            $path,
            $info['basename'],
            $mime,
            null,
            true
        );
    }

    public function uploadTestFileToRoute(
        string $route,
        array $args = [],
        string $key = 'document',
        string $fileName = null,
        string $extension = FileHelper::FILE_EXTENSION_PDF
    ): void {
        $fileName = $fileName ?: $this->FILE_NAME_50Kb;

        $this->uploadTestFile(
            $this->url($route, $args),
            $key,
            $fileName,
            $extension
        );
    }

    public function uploadTestFile(
        string $path,
        string $key = 'document',
        string $fileName = null,
        string $extension = FileHelper::FILE_EXTENSION_PDF
    ): void {
        $fileName = $fileName ?: $this->FILE_NAME_50Kb;

        $pdfPath = $this->getFileTestPath(
            $fileName,
            $extension
        );

        $tmpDir = $this->getStorageDir('tmp');
        $info = pathinfo($pdfPath);
        $fileName = $info['filename'];
        $pdfPathTmpCopy = $tmpDir.$info['basename'];

        copy(
            $pdfPath,
            $pdfPathTmpCopy
        );

        $uploadFile = new UploadedFile(
            $pdfPathTmpCopy,
            $fileName,
            $this->FILE_MIME_TYPE[$extension]
        );

        $this->client->request(
            Request::METHOD_GET,
            $path,
            [],
            [
                $key => $uploadFile,
            ]
        );
    }
}
