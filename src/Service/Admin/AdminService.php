<?php

namespace App\Service\Admin;

use App\Entity\Category;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ResourceRepository;
use App\Repository\UserRepository;
use App\Repository\StatisticsRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ResourceRepository $resourceRepository,
        private CategoryRepository $categoryRepository,
        private UserRepository $userRepository,
    ) {}

    // --- Ressources ---

    public function getAllResources(): array
    {
        return $this->resourceRepository->findAll();
    }

    public function deleteResource(int $id): void
    {
        $resource = $this->resourceRepository->find($id);

        if (!$resource) {
            throw new \Exception('Ressource introuvable');
        }

        $this->em->remove($resource);
        $this->em->flush();
    }

    // --- Catégories ---

    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }

    public function createCategory(array $data): Category
    {
        $category = new Category();
        $category->setName($data['name']);
        $category->setDescription($data['description'] ?? null);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    public function updateCategory(int $id, array $data): Category
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            throw new \Exception('Catégorie introuvable');
        }

        if (!empty($data['name'])) {
            $category->setName($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $category->setDescription($data['description']);
        }

        $this->em->flush();

        return $category;
    }

    public function deleteCategory(int $id): void
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            throw new \Exception('Catégorie introuvable');
        }

        $this->em->remove($category);
        $this->em->flush();
    }

    // --- Utilisateurs ---

    public function getAllUsers(): array
    {
        return $this->userRepository->findAll();
    }

    public function deleteUser(int $id): void
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new \Exception('Utilisateur introuvable');
        }

        $this->em->remove($user);
        $this->em->flush();
    }

    public function banUser(int $id): void
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new \Exception('Utilisateur introuvable');
        }

        // Désactivation du compte (ajout d'un rôle BANNED ou is_verified=false)
        $user->setIsVerified(false);
        $this->em->flush();
    }

    // --- Statistiques ---

    public function getStatistics(): array
    {
        return [
            'total_resources' => $this->resourceRepository->count([]),
            'total_users'     => $this->userRepository->count([]),
            'pending'         => $this->resourceRepository->count(['status' => 'pending']),
            'published'       => $this->resourceRepository->count(['status' => 'published']),
        ];
    }

    public function exportStatistics(): array
    {
        // Retourne les stats sous format exportable (CSV, etc.)
        return $this->getStatistics();
    }
}
