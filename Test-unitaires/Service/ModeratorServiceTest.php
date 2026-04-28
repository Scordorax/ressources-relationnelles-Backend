<?php

namespace App\Tests\Service\Moderator;

use App\Entity\Comment;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\ResourceRepository;
use App\Service\Moderator\ModeratorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ModeratorService
 *
 * Couvre : getPendingResources(), validateResource(), rejectResource(),
 *          getReportedComments(), deleteComment(), replyComment()
 */
class ModeratorServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ResourceRepository $resourceRepository;
    private CommentRepository $commentRepository;
    private ModeratorService $service;

    protected function setUp(): void
    {
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->resourceRepository = $this->createMock(ResourceRepository::class);
        $this->commentRepository  = $this->createMock(CommentRepository::class);

        $this->service = new ModeratorService(
            $this->em,
            $this->resourceRepository,
            $this->commentRepository
        );
    }

    // ----------------------------------------------------------------
    //  getPendingResources()
    // ----------------------------------------------------------------

    public function testGetPendingResourcesQueriesByStatusPending(): void
    {
        $pending = new Resource();
        $pending->setStatus('pending');

        $this->resourceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'pending'])
            ->willReturn([$pending]);

        $result = $this->service->getPendingResources();

        $this->assertCount(1, $result);
        $this->assertSame($pending, $result[0]);
    }

    public function testGetPendingResourcesReturnsEmptyWhenNone(): void
    {
        $this->resourceRepository->method('findBy')->willReturn([]);

        $result = $this->service->getPendingResources();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    //  validateResource()
    // ----------------------------------------------------------------

    public function testValidateResourceSetsStatusToPublished(): void
    {
        $resource = new Resource();
        $resource->setStatus('pending');

        $this->resourceRepository->method('find')->with(1)->willReturn($resource);
        $this->em->expects($this->once())->method('flush');

        $this->service->validateResource(1);

        $this->assertSame('published', $resource->getStatus());
    }

    public function testValidateResourceThrowsWhenNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->validateResource(999);
    }

    public function testValidateResourceCallsFlush(): void
    {
        $resource = new Resource();
        $resource->setStatus('pending');

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->em->expects($this->once())->method('flush');

        $this->service->validateResource(1);
    }

    // ----------------------------------------------------------------
    //  rejectResource()
    // ----------------------------------------------------------------

    public function testRejectResourceSetsStatusToRejected(): void
    {
        $resource = new Resource();
        $resource->setStatus('pending');

        $this->resourceRepository->method('find')->with(2)->willReturn($resource);
        $this->em->expects($this->once())->method('flush');

        $this->service->rejectResource(2);

        $this->assertSame('rejected', $resource->getStatus());
    }

    public function testRejectResourceThrowsWhenNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->rejectResource(999);
    }

    public function testRejectResourceCallsFlush(): void
    {
        $resource = new Resource();
        $resource->setStatus('pending');

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->em->expects($this->once())->method('flush');

        $this->service->rejectResource(1);
    }

    // ----------------------------------------------------------------
    //  getReportedComments()
    // ----------------------------------------------------------------

    public function testGetReportedCommentsQueriesByIsReportedTrue(): void
    {
        $reported = new Comment();
        $reported->setContent('Commentaire signalé');
        $reported->setIsReported(true);

        $this->commentRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['isReported' => true])
            ->willReturn([$reported]);

        $result = $this->service->getReportedComments();

        $this->assertCount(1, $result);
        $this->assertSame($reported, $result[0]);
    }

    public function testGetReportedCommentsReturnsEmptyWhenNone(): void
    {
        $this->commentRepository->method('findBy')->willReturn([]);

        $result = $this->service->getReportedComments();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    //  deleteComment()
    // ----------------------------------------------------------------

    public function testDeleteCommentCallsRemoveAndFlush(): void
    {
        $comment = new Comment();
        $comment->setContent('À supprimer');

        $this->em->expects($this->once())->method('remove')->with($comment);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteComment($comment);
    }

    public function testDeleteCommentReturnsVoid(): void
    {
        $comment = new Comment();
        $comment->setContent('À supprimer');

        $this->em->method('remove');
        $this->em->method('flush');

        $result = $this->service->deleteComment($comment);

        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    //  replyComment()
    // ----------------------------------------------------------------

    public function testReplyCommentReturnsCommentInstance(): void
    {
        $resource = new Resource();
        $resource->setTitle('Ressource');
        $resource->setContent('Contenu');

        $parent = new Comment();
        $parent->setContent('Commentaire signalé');
        $parent->setResource($resource);

        $moderator = new User();
        $moderator->setEmail('mod@example.com');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $reply = $this->service->replyComment($parent, $moderator, 'Réponse du modérateur');

        $this->assertInstanceOf(Comment::class, $reply);
    }

    public function testReplyCommentSetsCorrectContent(): void
    {
        $resource = new Resource();
        $resource->setTitle('R');
        $resource->setContent('C');

        $parent = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->replyComment($parent, new User(), 'Contenu de la réponse');

        $this->assertSame('Contenu de la réponse', $reply->getContent());
    }

    public function testReplyCommentSetsParent(): void
    {
        $resource = new Resource();
        $resource->setTitle('R');
        $resource->setContent('C');

        $parent = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->replyComment($parent, new User(), 'Réponse');

        $this->assertSame($parent, $reply->getParent());
    }

    public function testReplyCommentSetsModerator(): void
    {
        $resource = new Resource();
        $resource->setTitle('R');
        $resource->setContent('C');

        $parent = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $moderator = new User();
        $moderator->setEmail('moderateur@example.com');

        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->replyComment($parent, $moderator, 'Réponse');

        $this->assertSame($moderator, $reply->getUser());
    }

    public function testReplyCommentLinksToSameResourceAsParent(): void
    {
        $resource = new Resource();
        $resource->setTitle('Ressource liée');
        $resource->setContent('Contenu');

        $parent = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->replyComment($parent, new User(), 'Réponse');

        $this->assertSame(
            $resource,
            $reply->getResource(),
            'La réponse doit être liée à la même ressource que le commentaire parent.'
        );
    }

    public function testReplyCommentPersistsAndFlushes(): void
    {
        $resource = new Resource();
        $resource->setTitle('R');
        $resource->setContent('C');

        $parent = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->replyComment($parent, new User(), 'Réponse');
    }
}
