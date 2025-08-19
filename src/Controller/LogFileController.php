<?php
declare(strict_types=1);

namespace InfoPlusCommerce\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

#[Route(defaults: ['_routeScope' => ['api']])]
class LogFileController extends AbstractController
{
    private string $projectDir;
    private RequestStack $requestStack;

    public function __construct(ParameterBagInterface $params, RequestStack $requestStack)
    {
        $this->projectDir = $params->get('kernel.project_dir');
        $this->requestStack = $requestStack;
    }

    #[Route(path: '/api/_action/infoplus/logs', name: 'api.infoPlus.logs', methods: ['GET'])]
    public function logs(): JsonResponse
    {
        $logs = [];
        try {
            $logDir = $this->projectDir . '/var/log';

            if (!is_dir($logDir)) {
                return new JsonResponse(['error' => 'Log directory not found'], 500);
            }

            $files = scandir($logDir, SCANDIR_SORT_DESCENDING);
            if (!is_array($files)) {
                return new JsonResponse(['error' => 'Unable to read log directory'], 500);
            }

            foreach ($files as $file) {
                if (str_starts_with($file, 'InfoPlus')) {
                    $path = $logDir . '/' . $file;
                    if (!is_file($path) || !is_readable($path)) {
                        continue;
                    }

                    $firstLines = [];
                    $fh = new \SplFileObject($path, 'r');
                    for ($i = 0; $i < 1 && !$fh->eof(); $i++) {
                        $line = $fh->fgets();
                        if ($line === false) {
                            break;
                        }
                        $firstLines[] = substr(rtrim($line, "\r\n"),0,500);
                    }

                    $logs[] = [
                        'file' => $file,
                        'content' => $firstLines,
                    ];
                }
            }
        }
        catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred while retrieving logs: ' . $e->getMessage()], 500);
        }
        return new JsonResponse(['logs' => $logs]);
    }

#[Route(
        path: '/api/_action/infoplus/logs/{file}/content',
        name: 'api.infoplus.logs.file',
        methods: ['GET'],
        defaults: ['_routeScope' => ['api']],
        requirements: ['file' => '[A-Za-z0-9._\-]+' ]
    )]
    public function getLogContent(string $file): JsonResponse
    {
        $logDir = $this->projectDir . '/var/log';
        $filePath = $logDir . '/' . $file;

        if (!is_file($filePath) || !is_readable($filePath)) {
            return new JsonResponse(['error' => 'Log file not found or not readable'], 404);
        }

        $page = (int)($this->requestStack->getCurrentRequest()->query->get('page', 1));
        $limit = (int)($this->requestStack->getCurrentRequest()->query->get('limit', 100));
        $startLine = ($page - 1) * $limit;
        $lines = [];
        $total = 0;
        try {
            $fh = new \SplFileObject($filePath, 'r');
            // Count total lines
            while (!$fh->eof()) {
                $fh->fgets();
                $total++;
            }
            $fh->rewind();
            // Seek to start line
            for ($i = 0; $i < $startLine && !$fh->eof(); $i++) {
                $fh->fgets();
            }
            // Read chunk
            for ($i = 0; $i < $limit && !$fh->eof(); $i++) {
                $line = $fh->fgets();
                if ($line === false) {
                    break;
                }
                $lines[] = rtrim($line, "\r\n");
            }
            return new JsonResponse(['lines' => $lines, 'total' => $total]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred while reading the log file: ' . $e->getMessage()], 500);
        }
    }
    #[Route(
        path: '/api/_action/infoplus/logs/{file}/download',
        name: 'api.infoplus.logs.file.download',
        methods: ['GET'],
        requirements: ['file' => '[A-Za-z0-9._\-]+']
    )]
    public function downloadLogFile(string $file): StreamedResponse|JsonResponse
    {
        $logDir = $this->projectDir . '/var/log';

        // Validate file name and path
        if (!preg_match('/^[A-Za-z0-9._\-]+$/', $file)) {
            return new JsonResponse(['error' => 'Invalid file name'], 400);
        }

        $filePath = $logDir . '/' . $file;
        $realPath = realpath($filePath);

        // Security check - ensure file is within log directory
        if ($realPath === false || !str_starts_with($realPath, realpath($logDir) . DIRECTORY_SEPARATOR)) {
            return new JsonResponse(['error' => 'Invalid path'], 400);
        }

        if (!is_file($realPath) || !is_readable($realPath)) {
            return new JsonResponse(['error' => 'Log file not found or not readable'], 404);
        }

        try {
            $response = new BinaryFileResponse($realPath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $file
            );
            $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
            $response = new StreamedResponse(function() use ($realPath) {
                readfile($realPath);
            });

            $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
            $response->headers->set(
                'Content-Disposition',
                $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $file
                )
            );
            return $response;
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Download failed: ' . $e->getMessage()], 500);
        }
    }
}
