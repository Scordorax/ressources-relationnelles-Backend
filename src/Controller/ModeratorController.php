<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Service\Moderator\ModeratorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/moderator')]
#[IsGranted('ROLE_MODERATOR')]
class ModeratorController extends AbstractController
{
    public function __construct(private ModeratorService $service) {}

    // Ressources en attente de validation
    #[Route('/resources/pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        return $this->json($this->service->getPendingResources());
    }

    // Valider une ressource pour publication
    #[Route('/resources/{id}/validate', methods: ['PUT'])]
    public function validate(int $id): JsonResponse
    {
        try {
            $this->service->validateResource($id);
            return $this->json(['message' => 'Ressource publiée']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    // Rejeter une ressource
    #[Route('/resources/{id}/reject', methods: ['PUT'])]
    public function reject(int $id): JsonResponse
    {
        try {
            $this->service->rejectResource($id);
            return $this->json(['message' => 'Ressource rejetée']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    // Commentaires signalés
    #[Route('/comments/reported', methods: ['GET'])]
    public function reportedComments(): JsonResponse
    {
        return $this->json($this->service->getReportedComments());
    }

    // Supprimer un commentaire
    #[Route('/comments/{id}', methods: ['DELETE'])]
    public function deleteComment(Comment $comment): JsonResponse
    {
        $this->service->deleteComment($comment);
        return $this->json(['message' => 'Commentaire supprimé']);
    }

    // Répondre à un commentaire (modération)
    #[Route('/comments/{id}/reply', methods: ['POST'])]
    public function replyComment(Comment $comment, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reply = $this->service->replyComment($comment, $this->getUser(), $data['content']);
        return $this->json($reply, Response::HTTP_CREATED);
    }
}
