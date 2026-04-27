<?php

namespace App\Controller;

use App\Service\Admin\AdminService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(private AdminService $service) {}

    // --- Gestion des ressources ---

    #[Route('/resources', methods: ['GET'])]
    public function allResources(): JsonResponse
    {
        return $this->json($this->service->getAllResources());
    }

    #[Route('/resources/{id}', methods: ['DELETE'])]
    public function deleteResource(int $id): JsonResponse
    {
        $this->service->deleteResource($id);
        return $this->json(['message' => 'Ressource supprimée']);
    }

    // --- Gestion des catégories ---

    #[Route('/categories', methods: ['GET'])]
    public function listCategories(): JsonResponse
    {
        return $this->json($this->service->getAllCategories());
    }

    #[Route('/categories', methods: ['POST'])]
    public function createCategory(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $category = $this->service->createCategory($data);
        return $this->json($category, JsonResponse::HTTP_CREATED);
    }

    #[Route('/categories/{id}', methods: ['PUT'])]
    public function updateCategory(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $category = $this->service->updateCategory($id, $data);
        return $this->json($category);
    }

    #[Route('/categories/{id}', methods: ['DELETE'])]
    public function deleteCategory(int $id): JsonResponse
    {
        $this->service->deleteCategory($id);
        return $this->json(['message' => 'Catégorie supprimée']);
    }

    // --- Gestion des comptes citoyens ---

    #[Route('/users', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        return $this->json($this->service->getAllUsers());
    }

    #[Route('/users/{id}', methods: ['DELETE'])]
    public function deleteUser(int $id): JsonResponse
    {
        $this->service->deleteUser($id);
        return $this->json(['message' => 'Compte supprimé']);
    }

    #[Route('/users/{id}/ban', methods: ['PUT'])]
    public function banUser(int $id): JsonResponse
    {
        $this->service->banUser($id);
        return $this->json(['message' => 'Utilisateur banni']);
    }

    // --- Statistiques ---

    #[Route('/statistics', methods: ['GET'])]
    public function statistics(): JsonResponse
    {
        return $this->json($this->service->getStatistics());
    }

    #[Route('/statistics/export', methods: ['GET'])]
    public function exportStatistics(): JsonResponse
    {
        return $this->json($this->service->exportStatistics());
    }
}
