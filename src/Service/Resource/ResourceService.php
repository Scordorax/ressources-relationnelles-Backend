<?php

namespace App\Service\Resource;

use App\Entity\Resource;
use App\Entity\User;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResourceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ResourceRepository $repository
    ) {}

    /**
     * Retourne les ressources publiées.
     * Si l'utilisateur est connecté, inclut aussi les ressources restreintes.
     */
    public function getAll(?User $user): array
    {
        if ($user) {
            // Connecté : publiées publiques + restreintes
            return $this->repository->findBy(['status' => 'published']);
        }

        // Non connecté : publiées et publiques uniquement
        return $this->repository->findBy([
            'status'     => 'published',
            'visibility' => 'public',
        ]);
    }

    public function getOne(Resource $resource): Resource
    {
        return $resource;
    }

    public function create(array $data, User $user): Resource
    {
        $this->validateData($data);

        $resource = new Resource();
        $resource->setTitle($data['title']);
        $resource->setContent($data['content']);
        $resource->setType($data['type']);
        $resource->setStatus('pending');
        $resource->setVisibility($data['visibility'] ?? 'public');
        $resource->setAuthor($user);

        if (!empty($data['category_id'])) {
            $category = $this->em->getReference(\App\Entity\Category::class, $data['category_id']);
            $resource->setCategory($category);
        }

        $this->em->persist($resource);
        $this->em->flush();

        return $resource;
    }

    public function update(Resource $resource, array $data): Resource
    {
        if (!empty($data['title'])) {
            $resource->setTitle($data['title']);
        }

        if (!empty($data['content'])) {
            $resource->setContent($data['content']);
        }

        if (!empty($data['visibility'])) {
            $resource->setVisibility($data['visibility']);
        }

        // Repasse en pending après modification
        $resource->setStatus('pending');
        $resource->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $resource;
    }

    public function delete(Resource $resource): void
    {
        $this->em->remove($resource);
        $this->em->flush();
    }

    private function validateData(array $data): void
    {
        foreach (['title', 'content', 'type'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Le champ '$field' est requis");
            }
        }
    }
}
