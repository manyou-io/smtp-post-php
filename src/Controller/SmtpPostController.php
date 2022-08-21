<?php

declare(strict_types=1);

namespace App\Controller;

use App\SmtpPost\Backend;
use App\SmtpPost\InvalidRequestException;
use App\SmtpPost\Message;
use App\SmtpPost\SendException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function array_map;
use function explode;

class SmtpPostController extends AbstractController
{
    public function __construct(private Backend $backend, private LoggerInterface $logger)
    {
    }

    #[Route('/', name: 'smtp_post', methods: ['POST'])]
    public function index(Request $request): Response
    {
        $message = $this->createMessage($request);

        try {
            $this->backend->send($message);
        } catch (InvalidRequestException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (SendException $e) {
            $this->logger->error(SendException::class, [
                'from' => $message->from,
                'to' => $message->to,
                'headers' => $request->headers->all(),
                'client_ips' => $request->getClientIps(),
            ]);

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response();
    }

    private function createMessage(Request $request): Message
    {
        $data = $request->getContent();
        $from = $request->headers->get('X-Mail-From');
        $to   = $request->headers->get('X-Rcpt-To');

        return new Message($from, array_map('\trim', explode(',', $to)), $data);
    }
}
