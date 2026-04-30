<?php

namespace App\Controller;

use App\Service\Favorites\FavoritesService;
use App\Service\Resource\ResourceService;
use App\Service\ResourceInteraction\ResourceInteractionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class FavoritesController extends AbstractController
{

    public function __construct(private FavoritesService $service)
    {
    }

    #[Route('/resources/favorites', methods: ['GET'])]
    public function favorites(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $interactions = $this->service->getFavoritesByUser($this->getUser());

        return $this->json(array_map(function ($interaction) {
            $resource = $interaction->getResource();

            return [
                'id' => $resource->getId(),
                'title' => $resource->getTitle(),
                'content' => $resource->getContent(),
                'type' => $resource->getType(),
            ];
        }, $interactions));
    }

    #[Route('/resources/favorites/aside', methods: ['GET'])]
    public function getAsides(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $interactions = $this->service->getByUserAndType(
            $this->getUser(),
            'aside'
        );

        $data = array_map(fn($i) => [
            'id' => $i->getId(),
            'type' => $i->getType(),
            'createdAt' => $i->getCreatedAt()?->format(DATE_ATOM),
            'resource' => [
                'id' => $i->getResource()->getId(),
                'title' => $i->getResource()->getTitle(),
                'content' => $i->getResource()->getContent(),
                'type' => $i->getResource()->getType(),
            ]
        ], $interactions);

        return $this->json($data);
    }

    #[Route('/resources/pending', methods: ['GET'])]
    public function getPending(ResourceService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $resources = $this->service->getByStatusAndUser(
            $this->getUser(),
            'pending'
        );

        $data = array_map(fn($r) => [
            'id' => $r->getId(),
            'title' => $r->getTitle(),
            'content' => $r->getContent(),
            'type' => $r->getType(),
            'status' => $r->getStatus(),
            'createdAt' => $r->getCreatedAt()?->format(DATE_ATOM),

            'category' => $r->getCategory() ? [
                'id' => $r->getCategory()->getId(),
                'name' => $r->getCategory()->getName(),
            ] : null,
        ], $resources);

        return $this->json($data);
    }

    #[Route('/moderator/pendingAll', methods: ['GET'])]
    public function getPendingAll(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $resources = $this->service->getByStatus('pending');

        $data = array_map(fn($r) => [
            'id' => $r->getId(),
            'title' => $r->getTitle(),
            'content' => $r->getContent(),
            'type' => $r->getType(),
            'status' => $r->getStatus(),
            'createdAt' => $r->getCreatedAt()?->format(DATE_ATOM),

            'category' => $r->getCategory() ? [
                'id' => $r->getCategory()->getId(),
                'name' => $r->getCategory()->getName(),
            ] : null,

            'author' => $r->getAuthor() ? [
                'id' => $r->getAuthor()->getId(),
                'firstname' => $r->getAuthor()->getFirstname(),
                'lastname' => $r->getAuthor()->getLastname(),
            ] : null,

        ], $resources);

        return $this->json($data);
    }

    #[Route('/resources/draft', methods: ['GET'])]
    public function getDraft(ResourceService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $resources = $this->service->getByStatusAndUser(
            $this->getUser(),
            'draft'
        );

        $data = array_map(fn($r) => [
            'id' => $r->getId(),
            'title' => $r->getTitle(),
            'content' => $r->getContent(),
            'type' => $r->getType(),
            'status' => $r->getStatus(),
            'createdAt' => $r->getCreatedAt()?->format(DATE_ATOM),

            'category' => $r->getCategory() ? [
                'id' => $r->getCategory()->getId(),
                'name' => $r->getCategory()->getName(),
            ] : null,
        ], $resources);

        return $this->json($data);
    }

}
