<?php

declare(strict_types=1);

namespace App\Controller\Chat;

use Anthropic\Core\Exceptions\APIException;
use App\Chat\ChatService;
use App\Chat\ConversationStore;
use App\Form\ChatInput;
use App\Form\ChatInputType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SendController extends AbstractController
{
    #[Route('/chat', name: 'chat_send', methods: ['POST'])]
    public function __invoke(Request $request, ChatService $chat, ConversationStore $store): Response
    {
        $input = new ChatInput();
        $form = $this->createForm(ChatInputType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('chat/index.html.twig', [
                'messages' => $store->messages(),
                'form' => $form,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        try {
            $chat->ask($input->message);
        } catch (APIException $e) {
            $this->addFlash('error', 'De call naar de API mislukte: '.$e->getMessage());
        }

        // Het gesprek staat nu in de store, dus gewoon terug naar de overzichtspagina.
        return $this->redirectToRoute('chat', status: Response::HTTP_SEE_OTHER);
    }
}
