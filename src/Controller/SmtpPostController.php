<?php

declare(strict_types=1);

namespace App\Controller;

use App\SmtpPost\Backend;
use App\SmtpPost\InvalidRequestException;
use App\SmtpPost\Message;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SmtpPostController extends AbstractController
{
    public function __construct(private Backend $backend)
    {
    }

    #[Route('/', name: 'smtp_post', methods: ['POST'])]
    public function index(Request $request): Response
    {
        try {
            $this->backend->send($this->createMessage($request));
        } catch (InvalidRequestException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new Response();
    }

    private function createMessage(Request $request): Message
    {
        $data = $request->getContent();
        $from = $request->headers->get('X-Mail-From');
        $to   = $request->headers->get('X-Rcpt-To');

        return new Message($from, explode(', ', $to), $data);
    }
}
