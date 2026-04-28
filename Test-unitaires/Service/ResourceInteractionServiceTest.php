<?php

namespace App\Tests\Service\ResourceInteraction;

use App\Entity\Resource;
use App\Entity\ResourceInteraction;
use App\Entity\User;
use App\Repository\ResourceInteractionRepository;
use App\Repository\ResourceRepository;
use App\Service\ResourceInteraction\ResourceInteractionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ResourceInteractionService
 *
 * Couvre : interact(), getByUserAndResource(), remove()
 *
 * Types autorisés : 'favorite', 'progress', 'aside', 'share'
 *
 * interact() est idempotent : si l'interaction existe déjà,
 * elle est renvoyée sans recréer un doublon.
 */
class ResourceInteractionServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ResourceInteractionRepository $repository;
    private ResourceRepository $resourceRepository;
    private ResourceInteractionService $service;

    protected function setUp(): void
    {
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->repository         = $this->createMock(ResourceInteractionRepository::class);
        $this->resourceRepository = $this->createMock(ResourceRepository::class);

        $this->service = new ResourceInteractionService(
            $this->em,
            $this->repository,
            $this->resourceRepository
        );
    }

    // ----------------------------------------------------------------
    //  interact()
    // ----------------------------------------------------------------

    public function testInteractCreatesNewFavoriteInteraction(): void
    {
        $user     = new User();
        $resource = new Resource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn(null); // pas encore d'interaction
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->interact($user, 1, 'favorite');

        $this->assertInstanceOf(ResourceInteraction::class, $result);
        $this->assertSame('favorite', $result->getType());
    }

    public function testInteractLinksInteractionToUser(): void
    {
        $user     = new User();
        $user->setEmail('user@example.com');
        $resource = new Resource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn(null);
        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->interact($user, 1, 'progress');

        $this->assertSame($user, $result->getUser());
    }

    public function testInteractLinksInteractionToResource(): void
    {
        $user     = new User();
        $resource = new Resource();
        $resource->setTitle('Ma ressource');

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn(null);
        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->interact($user, 1, 'aside');

        $this->assertSame($resource, $result->getResource());
    }

    public function testInteractAllAllowedTypesWork(): void
    {
        $user     = new User();
        $resource = new Resource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn(null);
        $this->em->method('persist');
        $this->em->method('flush');

        foreach (['favorite', 'progress', 'aside', 'share'] as $type) {
            $result = $this->service->interact($user, 1, $type);
            $this->assertSame($type, $result->getType(), "Le type '$type' doit être accepté.");
        }
    }

    public function testInteractIsIdempotentWhenInteractionAlreadyExists(): void
    {
        $user        = new User();
        $resource    = new Resource();
        $existing    = new ResourceInteraction();
        $existing->setType('favorite');
        $existing->setUser($user);
        $existing->setResource($resource);

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn($existing);

        // NE doit PAS persister ni flush
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->interact($user, 1, 'favorite');

        $this->assertSame($existing, $result, 'L\'interaction existante doit être retournée sans doublon.');
    }

    public function testInteractThrowsOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Type d'interaction invalide");

        $this->service->interact(new User(), 1, 'like'); // non autorisé
    }

    public function testInteractThrowsOnEmptyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Type d'interaction invalide");

        $this->service->interact(new User(), 1, '');
    }

    public function testInteractThrowsWhenResourceNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->interact(new User(), 999, 'favorite');
    }

    public function testInteractValidatesTypeBeforeCheckingResource(): void
    {
        // Le type invalide doit provoquer une exception avant même l'accès repo
        $this->resourceRepository->expects($this->never())->method('find');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->interact(new User(), 1, 'invalid_type');
    }

    // ----------------------------------------------------------------
    //  getByUserAndResource()
    // ----------------------------------------------------------------

    public function testGetByUserAndResourceReturnsInteractions(): void
    {
        $user        = new User();
        $resource    = new Resource();
        $interaction = new ResourceInteraction();

        $this->resourceRepository->method('find')->with(1)->willReturn($resource);
        $this->repository
            ->method('findBy')
            ->with(['user' => $user, 'resource' => $resource])
            ->willReturn([$interaction]);

        $result = $this->service->getByUserAndResource($user, 1);

        $this->assertCount(1, $result);
        $this->assertSame($interaction, $result[0]);
    }

    public function testGetByUserAndResourceReturnsEmptyWhenResourceNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $result = $this->service->getByUserAndResource(new User(), 999);

        // Ne doit PAS lever d'exception — retourne tableau vide
        $this->assertSame([], $result);
    }

    public function testGetByUserAndResourceReturnsEmptyWhenNoInteractions(): void
    {
        $user     = new User();
        $resource = new Resource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findBy')->willReturn([]);

        $result = $this->service->getByUserAndResource($user, 1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    //  remove()
    // ----------------------------------------------------------------

    public function testRemoveDeletesExistingInteraction(): void
    {
        $user        = new User();
        $resource    = new Resource();
        $interaction = new ResourceInteraction();

        $this->resourceRepository->method('find')->with(1)->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn($interaction);
        $this->em->expects($this->once())->method('remove')->with($interaction);
        $this->em->expects($this->once())->method('flush');

        $this->service->remove($user, 1, 'favorite');
    }

    public function testRemoveDoesNothingWhenInteractionDoesNotExist(): void
    {
        $user     = new User();
        $resource = new Resource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn(null);

        // NE doit PAS appeler remove ou flush
        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $this->service->remove($user, 1, 'favorite');
    }

    public function testRemoveThrowsWhenResourceNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->remove(new User(), 999, 'favorite');
    }

    public function testRemoveReturnsVoid(): void
    {
        $user     = new User();
        $resource = new Resource();

        $this->resourceRepository->method('find')->willReturn($resource);
        $this->repository->method('findOneBy')->willReturn(null);

        $result = $this->service->remove($user, 1, 'favorite');

        $this->assertNull($result);
    }
}
