<?php

namespace App\Controller;

use App\Service\ResourceInteraction\ResourceInteractionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/resources/{id}/interactions')]
class ResourceInteractionController extends AbstractController
{
    public function __construct(private ResourceInteractionService $service) {}

    // CONNECTÉ — ajouter une interaction (favorite, progress, aside, share)
    #[Route('', methods: ['POST'])]
    public function interact(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        try {
            $interaction = $this->service->interact($this->getUser(), $id, $data['type']);
            return $this->json($interaction, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // CONNECTÉ — lister ses interactions sur une ressource
    #[Route('', methods: ['GET'])]
    public function list(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json($this->service->getByUserAndResource($this->getUser(), $id));
    }

    // CONNECTÉ — supprimer une interaction
    #[Route('/{type}', methods: ['DELETE'])]
    public function remove(int $id, string $type): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $this->service->remove($this->getUser(), $id, $type);

        return $this->json(['message' => 'Interaction supprimée']);
    }
}
