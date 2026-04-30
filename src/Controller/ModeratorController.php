<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Service\Comment\CommentService;
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
    public function __construct(private ModeratorService $service, private CommentService $serviceComment)
    {
    }

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

    #[Route('/reported', methods: ['GET'])]
    public function getReported(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $comments = $this->serviceComment->getReported();

        $data = array_map(fn(Comment $c) => [
            'id' => $c->getId(),
            'content' => $c->getContent(),
            'createdAt' => $c->getCreatedAt()?->format(DATE_ATOM),

            'isReported' => $c->isReported(),

            'author' => $c->getUser() ? [
                'id' => $c->getUser()->getId(),
                'firstname' => $c->getUser()->getFirstname(),
                'lastname' => $c->getUser()->getLastname(),
            ] : null,

            'resource' => $c->getResource() ? [
                'id' => $c->getResource()->getId(),
                'title' => $c->getResource()->getTitle(),
            ] : null,

        ], $comments);

        return $this->json($data);
    }

    #[Route('/{id}/validate', methods: ['POST'])]
    public function validated(Comment $comment): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $this->serviceComment->validate($comment);

        return $this->json([
            'message' => 'Commentaire validé'
        ]);
    }

    #[Route('/{id}/reject', methods: ['POST'])]
    public function rejected(Comment $comment): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $this->serviceComment->reject($comment);

        return $this->json([
            'message' => 'Commentaire supprimé'
        ]);
    }


}
