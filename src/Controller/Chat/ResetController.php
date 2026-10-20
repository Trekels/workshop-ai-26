<?php

declare(strict_types=1);

namespace App\Controller\Chat;

use App\Chat\ConversationStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ResetController extends AbstractController
{
    #[Route('/chat/reset', name: 'chat_reset', methods: ['POST'])]
    #[IsCsrfTokenValid('chat_reset')]
    public function __invoke(ConversationStore $store): Response
    {
        $store->clear();

        return $this->redirectToRoute('chat', status: Response::HTTP_SEE_OTHER);
    }
}
