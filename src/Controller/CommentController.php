<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Resource;
use App\Service\Comment\CommentService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/resources/{resourceId}/comments')]
class CommentController extends AbstractController
{
    public function __construct(private CommentService $service) {}

    // PUBLIC — lister les commentaires d'une ressource
    #[Route('', methods: ['GET'])]
    public function list(int $resourceId): JsonResponse
    {
        return $this->json($this->service->getByResourceId($resourceId));
    }

    // CONNECTÉ — poster un commentaire
    #[Route('', methods: ['POST'])]
    public function create(int $resourceId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        try {
            $comment = $this->service->create($data, $this->getUser(), $resourceId);
            return $this->json($comment, JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // CONNECTÉ — répondre à un commentaire
    #[Route('/{commentId}/reply', methods: ['POST'])]
    public function reply(int $resourceId, int $commentId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        try {
            $comment = $this->service->reply($data, $this->getUser(), $resourceId, $commentId);
            return $this->json($comment, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
