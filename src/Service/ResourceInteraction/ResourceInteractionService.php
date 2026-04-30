<?php

namespace App\Service\ResourceInteraction;

use App\Entity\ResourceInteraction;
use App\Entity\User;
use App\Repository\ResourceInteractionRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResourceInteractionService
{
    private const ALLOWED_TYPES = ['favorite', 'progress', 'aside', 'share'];

    public function __construct(
        private EntityManagerInterface $em,
        private ResourceInteractionRepository $repository,
        private ResourceRepository $resourceRepository
    ) {}

    public function interact(User $user, int $resourceId, string $type): ResourceInteraction
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException('Type d\'interaction invalide');
        }

        $resource = $this->resourceRepository->find($resourceId);

        if (!$resource) {
            throw new \Exception('Ressource introuvable');
        }

        // Vérifie si l'interaction existe déjà
        $existing = $this->repository->findOneBy([
            'user'     => $user,
            'resource' => $resource,
            'type'     => $type,
        ]);

        if ($existing) {
            return $existing; // Idempotent
        }

        $interaction = new ResourceInteraction();
        $interaction->setUser($user);
        $interaction->setResource($resource);
        $interaction->setType($type);

        $this->em->persist($interaction);
        $this->em->flush();

        return $interaction;
    }

    public function getByUserAndResource(User $user, int $resourceId): array
    {
        $resource = $this->resourceRepository->find($resourceId);

        if (!$resource) {
            return [];
        }

        return $this->repository->findBy([
            'user'     => $user,
            'resource' => $resource,
        ]);
    }

    public function remove(User $user, int $resourceId, string $type): void
    {
        $resource = $this->resourceRepository->find($resourceId);

        if (!$resource) {
            throw new \Exception('Ressource introuvable');
        }

        $interaction = $this->repository->findOneBy([
            'user'     => $user,
            'resource' => $resource,
            'type'     => $type,
        ]);

        if ($interaction) {
            $this->em->remove($interaction);
            $this->em->flush();
        }
    }


}
