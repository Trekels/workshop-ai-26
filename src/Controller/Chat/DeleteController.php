<?php

declare(strict_types=1);

namespace App\Controller\Chat;

use App\Chat\ConversationStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Recht op wissen (AVG art. 17): de gebruiker kan zijn eigen gesprek definitief verwijderen, zonder iets te vragen.
 */
final class DeleteController extends AbstractController
{
    #[Route('/chat/delete', name: 'chat_delete', methods: ['POST'])]
    #[IsCsrfTokenValid('chat_delete')]
    public function __invoke(ConversationStore $store): Response
    {
        $store->delete();
        $this->addFlash('info', 'Je gesprek is gewist. Ook bij ons.');

        return $this->redirectToRoute('chat', status: Response::HTTP_SEE_OTHER);
    }
}
