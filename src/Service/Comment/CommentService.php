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

    public function getCommentById(int $id): ?Comment
    {
        return $this->commentRepository->find($id);
    }

    public function getByResourceId(int $resourceId): array
    {
        $resource = $this->getResource($resourceId);

        return $this->commentRepository->findBy(
            ['resource' => $resource],
            ['createdAt' => 'DESC']
        );
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

        // 👇 important : compteur à 0
        $comment->setIsReported(0);

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

        $comment->setIsReported(0);

        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }

    /* ───────────────────────────────
     * 🚨 COMMENTAIRES SIGNALÉS
     * ─────────────────────────────── */

    public function getReported(): array
    {
        // 👇 tous les commentaires avec au moins 1 signalement
        return $this->commentRepository->createQueryBuilder('c')
            ->where('c.isReported > 0')
            ->orderBy('c.isReported', 'DESC') // les plus signalés en premier
            ->getQuery()
            ->getResult();
    }

    public function validate(Comment $comment): Comment
    {
        // ✅ on reset le compteur
        $comment->setIsReported(0);

        $this->em->flush();

        return $comment;
    }

    public function reject(Comment $comment): void
    {
        // ❌ suppression du commentaire
        $this->em->remove($comment);
        $this->em->flush();
    }

    /* ─────────────────────────────── */

    private function getResource(int $id): Resource
    {
        $resource = $this->resourceRepository->find($id);

        if (!$resource) {
            throw new \InvalidArgumentException('Ressource introuvable');
        }

        return $resource;
    }

    public function save(Comment $comment): void
    {
        $this->em->flush();
    }

    public function delete(Comment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }

    public function report(Comment $comment): Comment
    {
        // +1 au compteur de signalement
        $comment->setIsReported($comment->IsReported() + 1);

        $this->em->flush();

        return $comment;
    }
}
