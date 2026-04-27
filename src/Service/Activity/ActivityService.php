<?php

namespace App\Service\Activity;

use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;

class ActivityService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityRepository $repository
    ) {}

    public function getAll(): array
    {
        return $this->repository->findAll();
    }

    public function start(int $activityId, User $user): array
    {
        $activity = $this->repository->find($activityId);

        if (!$activity) {
            throw new \Exception('Activité introuvable');
        }

        // Logique de démarrage (ex : enregistrement de participation)
        return [
            'message'  => 'Activité démarrée',
            'activity' => [
                'id'   => $activity->getId(),
                'name' => $activity->getName(),
            ],
        ];
    }
}
