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
        $comments = $this->service->getByResourceId($resourceId);

        return $this->json(array_map(function (Comment $comment) {
            return [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()?->format(DATE_ATOM),

                'user' => $comment->getUser() ? [
                    'id' => $comment->getUser()->getId(),
                    'firstname' => $comment->getUser()->getFirstname(),
                    'lastname' => $comment->getUser()->getLastname(),
                ] : null,

                // IMPORTANT : pas de resource complète
                'resourceId' => $comment->getResource()?->getId(),

                'parentId' => $comment->getParent()?->getId(),
            ];
        }, $comments));
    }

    // CONNECTÉ — poster un commentaire
    #[Route('', methods: ['POST'])]
    public function create(int $resourceId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        try {
            $comment = $this->service->create($data, $this->getUser(), $resourceId);

            return $this->json([
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()?->format(DATE_ATOM),

                'user' => [
                    'id' => $comment->getUser()->getId(),
                    'firstname' => $comment->getUser()->getFirstname(),
                    'lastname' => $comment->getUser()->getLastname(),
                ],

                'resource' => [
                    'id' => $comment->getResource()->getId(),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
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

            return $this->json([
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()?->format(DATE_ATOM),

                'user' => [
                    'id' => $comment->getUser()->getId(),
                    'firstname' => $comment->getUser()->getFirstname(),
                    'lastname' => $comment->getUser()->getLastname(),
                ],

                'resourceId' => $comment->getResource()->getId(),
                'parentId' => $comment->getParent()?->getId(),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{commentId}/report', methods: ['POST'])]
    public function report(int $resourceId, int $commentId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $comment = $this->service->getCommentById($commentId);

        if (!$comment) {
            return $this->json(['error' => 'Commentaire introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->service->report($comment);

        return $this->json([
            'message' => 'Commentaire signalé'
        ]);
    }
}
