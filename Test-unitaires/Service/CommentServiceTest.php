<?php

namespace App\Tests\Service\Comment;

use App\Entity\Comment;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\ResourceRepository;
use App\Service\Comment\CommentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CommentService
 *
 * Couvre : getByResourceId(), create(), reply()
 */
class CommentServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private CommentRepository $commentRepository;
    private ResourceRepository $resourceRepository;
    private CommentService $service;

    protected function setUp(): void
    {
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->commentRepository  = $this->createMock(CommentRepository::class);
        $this->resourceRepository = $this->createMock(ResourceRepository::class);

        $this->service = new CommentService(
            $this->em,
            $this->commentRepository,
            $this->resourceRepository
        );
    }

    // ----------------------------------------------------------------
    //  Helper
    // ----------------------------------------------------------------

    private function buildResource(): Resource
    {
        $resource = new Resource();
        $resource->setTitle('Ressource Test');
        $resource->setContent('Corps');
        $resource->setType('article');
        return $resource;
    }

    // ----------------------------------------------------------------
    //  getByResourceId()
    // ----------------------------------------------------------------

    public function testGetByResourceIdReturnsRootCommentsOnly(): void
    {
        $resource = $this->buildResource();
        $comment  = new Comment();
        $comment->setContent('Commentaire racine');
        $comment->setResource($resource);

        $this->resourceRepository->method('find')->with(1)->willReturn($resource);

        // Vérifie que le critère parent => null est bien passé
        $this->commentRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['resource' => $resource, 'parent' => null])
            ->willReturn([$comment]);

        $result = $this->service->getByResourceId(1);

        $this->assertCount(1, $result);
        $this->assertSame($comment, $result[0]);
    }

    public function testGetByResourceIdThrowsWhenResourceNotFound(): void
    {
        $this->resourceRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->getByResourceId(999);
    }

    public function testGetByResourceIdReturnsEmptyArrayWhenNoComments(): void
    {
        $resource = $this->buildResource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->commentRepository->method('findBy')->willReturn([]);

        $result = $this->service->getByResourceId(1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    //  create()
    // ----------------------------------------------------------------

    public function testCreateReturnsCommentWithCorrectContent(): void
    {
        $resource = $this->buildResource();
        $user     = new User();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->em->method('persist');
        $this->em->method('flush');

        $comment = $this->service->create(['content' => 'Mon commentaire'], $user, 1);

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertSame('Mon commentaire', $comment->getContent());
    }

    public function testCreateLinksCommentToUser(): void
    {
        $resource = $this->buildResource();
        $user     = new User();
        $user->setEmail('auteur@example.com');

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->em->method('persist');
        $this->em->method('flush');

        $comment = $this->service->create(['content' => 'Commentaire'], $user, 1);

        $this->assertSame($user, $comment->getUser());
    }

    public function testCreateLinksCommentToResource(): void
    {
        $resource = $this->buildResource();
        $user     = new User();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->em->method('persist');
        $this->em->method('flush');

        $comment = $this->service->create(['content' => 'Commentaire'], $user, 1);

        $this->assertSame($resource, $comment->getResource());
    }

    public function testCreatePersistsAndFlushes(): void
    {
        $resource = $this->buildResource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->create(['content' => 'Commentaire'], new User(), 1);
    }

    public function testCreateThrowsWhenContentIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu est requis');

        $this->service->create(['content' => ''], new User(), 1);
    }

    public function testCreateThrowsWhenContentKeyMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu est requis');

        $this->service->create([], new User(), 1);
    }

    public function testCreateThrowsWhenResourceNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->create(['content' => 'Commentaire'], new User(), 999);
    }

    public function testCreateDoesNotSetParent(): void
    {
        $resource = $this->buildResource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->em->method('persist');
        $this->em->method('flush');

        $comment = $this->service->create(['content' => 'Commentaire racine'], new User(), 1);

        $this->assertNull($comment->getParent(), 'Un commentaire racine ne doit pas avoir de parent.');
    }

    // ----------------------------------------------------------------
    //  reply()
    // ----------------------------------------------------------------

    public function testReplyReturnsCommentWithCorrectContent(): void
    {
        $resource = $this->buildResource();
        $parent   = new Comment();
        $parent->setContent('Commentaire parent');
        $parent->setResource($resource);

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->commentRepository->method('find')->with(10)->willReturn($parent);
        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->reply(['content' => 'Ma réponse'], new User(), 1, 10);

        $this->assertInstanceOf(Comment::class, $reply);
        $this->assertSame('Ma réponse', $reply->getContent());
    }

    public function testReplySetsParentComment(): void
    {
        $resource = $this->buildResource();
        $parent   = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->commentRepository->method('find')->willReturn($parent);
        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->reply(['content' => 'Réponse'], new User(), 1, 10);

        $this->assertSame($parent, $reply->getParent());
    }

    public function testReplyLinksToSameResource(): void
    {
        $resource = $this->buildResource();
        $parent   = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $user = new User();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->commentRepository->method('find')->willReturn($parent);
        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->reply(['content' => 'Réponse'], $user, 1, 10);

        $this->assertSame($resource, $reply->getResource());
    }

    public function testReplyLinksToUser(): void
    {
        $resource  = $this->buildResource();
        $parent    = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);
        $user = new User();
        $user->setEmail('replier@example.com');

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->commentRepository->method('find')->willReturn($parent);
        $this->em->method('persist');
        $this->em->method('flush');

        $reply = $this->service->reply(['content' => 'Réponse'], $user, 1, 10);

        $this->assertSame($user, $reply->getUser());
    }

    public function testReplyThrowsWhenContentIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu est requis');

        $this->service->reply(['content' => ''], new User(), 1, 10);
    }

    public function testReplyThrowsWhenParentNotFound(): void
    {
        $resource = $this->buildResource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->commentRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Commentaire parent introuvable');

        $this->service->reply(['content' => 'Réponse'], new User(), 1, 999);
    }

    public function testReplyThrowsWhenResourceNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->reply(['content' => 'Réponse'], new User(), 999, 10);
    }

    public function testReplyPersistsAndFlushes(): void
    {
        $resource = $this->buildResource();
        $parent   = new Comment();
        $parent->setContent('Parent');
        $parent->setResource($resource);

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->commentRepository->method('find')->willReturn($parent);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->reply(['content' => 'Réponse'], new User(), 1, 10);
    }
}
