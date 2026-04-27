<?php

namespace App\Service\Comment;

use App\Entity\Comment;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

class CommentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CommentRepository $commentRepository,
        private ResourceRepository $resourceRepository
    ) {}

    public function getByResourceId(int $resourceId): array
    {
        $resource = $this->getResource($resourceId);

        // Retourne uniquement les commentaires racines (sans parent)
        return $this->commentRepository->findBy([
            'resource' => $resource,
            'parent'   => null,
        ]);
    }

    public function create(array $data, User $user, int $resourceId): Comment
    {
        if (empty($data['content'])) {
            throw new \InvalidArgumentException('Le contenu est requis');
        }

        $resource = $this->getResource($resourceId);

        $comment = new Comment();
        $comment->setContent($data['content']);
        $comment->setUser($user);
        $comment->setResource($resource);

        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    public function reply(array $data, User $user, int $resourceId, int $parentId): Comment
    {
        if (empty($data['content'])) {
            throw new \InvalidArgumentException('Le contenu est requis');
        }

        $resource = $this->getResource($resourceId);

        $parent = $this->commentRepository->find($parentId);

        if (!$parent) {
            throw new \InvalidArgumentException('Commentaire parent introuvable');
        }

        $comment = new Comment();
        $comment->setContent($data['content']);
        $comment->setUser($user);
        $comment->setResource($resource);
        $comment->setParent($parent);

        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    private function getResource(int $id): Resource
    {
        $resource = $this->resourceRepository->find($id);

        if (!$resource) {
            throw new \InvalidArgumentException('Ressource introuvable');
        }

        return $resource;
    }
}
