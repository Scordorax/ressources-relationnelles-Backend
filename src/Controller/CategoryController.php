<?php

namespace App\Controller;

use App\Entity\Category;
use App\Service\Category\CategoryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    public function __construct(private CategoryService $service) {}

    // PUBLIC
    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $categories = $this->service->getAll();

        return $this->json(array_map(function ($category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'createdAt' => $category->getCreatedAt()?->format(DATE_ATOM),
            ];
        }, $categories));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Category $category): JsonResponse
    {
        return $this->json([
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'resources' => array_map(function ($resource) {
                return [
                    'id' => $resource->getId(),
                    'title' => $resource->getTitle(),
                    'type' => $resource->getType(),
                ];
            }, $category->getResources()->toArray())
        ]);
    }

    // ADMIN
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        try {
            $category = $this->service->create($data);
            return $this->json($category, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Category $category, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        return $this->json($this->service->update($category, $data));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Category $category): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $this->service->delete($category);
        return $this->json(['message' => 'Catégorie supprimée']);
    }
}
