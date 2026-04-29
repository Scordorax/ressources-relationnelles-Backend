<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ResourceRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class StatsController extends AbstractController
{
    public function __construct(
        private ResourceRepository $resourceRepository,
        private UserRepository $userRepository,
        private CategoryRepository $categoryRepository
    ) {}

    #[Route('/stats', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'total_resources' => $this->resourceRepository->countAll(),
            'total_users' => $this->userRepository->countAll(),
            'pending' => $this->resourceRepository->countByStatus('pending'),
            'published' => $this->resourceRepository->countByStatus('published'),
            'categories' => $this->categoryRepository->countAll([]),
        ]);
    }
}
