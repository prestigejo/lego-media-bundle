<?php
declare(strict_types=1);
namespace Idk\LegoMediaBundle\Action;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Idk\LegoMediaBundle\Service\AttachmentManager;


final class DownloadAction{

    private $manager;

    public function __construct(AttachmentManager $manager)
    {
        $this->manager = $manager;
    }

    public function __invoke(Request $request): Response{
        $file = $this->manager->find((int)$request->get('id'));
        $path = $this->manager->getAbsolutePath($file);
        $response = new BinaryFileResponse($path);
        $mimeTypeGuesser = new FileinfoMimeTypeGuesser();
        $response->headers->set('Content-Type', ($mimeTypeGuesser->isSupported())? $mimeTypeGuesser->guess($path):'text/plain');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file->getFilename());

        return $response;
    }

}