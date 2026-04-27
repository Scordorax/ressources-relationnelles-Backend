<?php

namespace App\Controller;

use App\Entity\Resource;
use App\Service\Resource\ResourceService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/resources')]
class ResourceController extends AbstractController
{
    public function __construct(private ResourceService $service) {}

    // PUBLIC — liste les ressources publiées (+ restreintes si connecté)
    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json($this->service->getAll($user));
    }

    // PUBLIC/CONNECTÉ selon visibilité
    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Resource $resource): JsonResponse
    {
        // Les ressources restreintes nécessitent d'être connecté
        if ($resource->getVisibility() === 'private') {
            $this->denyAccessUnlessGranted('ROLE_USER');
        }

        return $this->json($this->service->getOne($resource));
    }

    // CONNECTÉ — création (passe en pending)
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        try {
            $resource = $this->service->create($data, $this->getUser());
            return $this->json($resource, JsonResponse::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // CONNECTÉ — édition (auteur uniquement)
    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Resource $resource, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($resource->getAuthor() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $resource = $this->service->update($resource, $data);

        return $this->json($resource);
    }

    // ADMIN — suppression
    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Resource $resource): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $this->service->delete($resource);

        return $this->json(['message' => 'Ressource supprimée'], Response::HTTP_OK);
    }
}
