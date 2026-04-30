<?php

namespace App\Service\Favorites;

use App\Entity\User;
use App\Repository\ResourceInteractionRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

class FavoritesService
{

    private const ALLOWED_TYPES = ['favorite', 'progress', 'aside', 'share'];

    public function __construct(
        private EntityManagerInterface $em,
        private ResourceInteractionRepository $repositoryInteraction,
        private ResourceRepository $resourceRepository
    ) {}

    public function getFavoritesByUser(User $user): array
    {
        return $this->repositoryInteraction->findBy([
            'user' => $user,
            'type' => 'favorite'
        ]);
    }

    public function getByUserAndType(User $user, string $type): array
    {
        return $this->repositoryInteraction->findBy([
            'user' => $user,
            'type' => $type
        ]);
    }

    public function getByStatusAndUser(User $user, string $status): array
    {
        return $this->resourceRepository->findBy([
            'author' => $user,
            'status' => $status
        ], ['createdAt' => 'DESC']);
    }

    public function getByStatus(string $status): array
    {
        return $this->resourceRepository->findBy([
            'status' => $status
        ], ['createdAt' => 'DESC']);
    }

}
