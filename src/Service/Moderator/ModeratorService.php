<?php

namespace App\Service\Moderator;

use App\Entity\Comment;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModeratorService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ResourceRepository $resourceRepository,
        private CommentRepository $commentRepository,
    ) {}

    public function getPendingResources(): array
    {
        return $this->resourceRepository->findBy(['status' => 'pending']);
    }

    public function validateResource(int $id): void
    {
        $resource = $this->getResource($id);
        $resource->setStatus('published');
        $this->em->flush();
    }

    public function rejectResource(int $id): void
    {
        $resource = $this->getResource($id);
        $resource->setStatus('rejected');
        $this->em->flush();
    }

    public function getReportedComments(): array
    {
        return $this->commentRepository->findBy(['isReported' => true]);
    }

    public function deleteComment(Comment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }

    public function replyComment(Comment $parent, User $moderator, string $content): Comment
    {
        $reply = new Comment();
        $reply->setContent($content);
        $reply->setUser($moderator);
        $reply->setResource($parent->getResource());
        $reply->setParent($parent);

        $this->em->persist($reply);
        $this->em->flush();

        return $reply;
    }

    private function getResource(int $id): Resource
    {
        $resource = $this->resourceRepository->find($id);

        if (!$resource) {
            throw new \Exception('Ressource introuvable');
        }

        return $resource;
    }
}
