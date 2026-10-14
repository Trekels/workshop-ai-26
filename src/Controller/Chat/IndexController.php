<?php

declare(strict_types=1);

namespace App\Controller\Chat;

use App\Chat\ConversationStore;
use App\Form\ChatInput;
use App\Form\ChatInputType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/', name: 'chat', methods: ['GET'])]
    public function __invoke(ConversationStore $store): Response
    {
        return $this->render('chat/index.html.twig', [
            'messages' => $store->messages(),
            'form' => $this->createForm(ChatInputType::class, new ChatInput()),
        ]);
    }
}
