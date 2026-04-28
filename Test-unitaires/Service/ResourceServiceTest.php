<?php

namespace App\Tests\Service\Resource;

use App\Entity\Resource;
use App\Entity\User;
use App\Repository\ResourceRepository;
use App\Service\Resource\ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ResourceService
 *
 * Couvre : getAll(), getOne(), create(), update(), delete()
 *
 * NOTE : getAll() pour un utilisateur connecté renvoie TOUTES les ressources
 * publiées (publiques + privées). N'importe quel utilisateur authentifié
 * peut voir les ressources privées d'autrui. Voir comments.md, problème #9.
 */
class ResourceServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ResourceRepository $repository;
    private ResourceService $service;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(ResourceRepository::class);

        $this->service = new ResourceService($this->em, $this->repository);
    }

    // ----------------------------------------------------------------
    //  getAll()
    // ----------------------------------------------------------------

    public function testGetAllForGuestQueriesOnlyPublicPublished(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'published', 'visibility' => 'public'])
            ->willReturn([]);

        $result = $this->service->getAll(null);

        $this->assertIsArray($result);
    }

    public function testGetAllForLoggedInUserQueriesAllPublished(): void
    {
        $user = new User();

        $this->repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'published'])
            ->willReturn([]);

        $result = $this->service->getAll($user);

        $this->assertIsArray($result);
    }

    public function testGetAllForGuestDoesNotIncludeRestrictedResources(): void
    {
        // Vérifie que le critère 'visibility' => 'public' est bien ajouté pour les guests
        $capturedCriteria = null;

        $this->repository
            ->method('findBy')
            ->willReturnCallback(function (array $criteria) use (&$capturedCriteria) {
                $capturedCriteria = $criteria;
                return [];
            });

        $this->service->getAll(null);

        $this->assertArrayHasKey('visibility', $capturedCriteria);
        $this->assertSame('public', $capturedCriteria['visibility']);
    }

    public function testGetAllForAuthenticatedUserDoesNotFilterByVisibility(): void
    {
        $capturedCriteria = null;

        $this->repository
            ->method('findBy')
            ->willReturnCallback(function (array $criteria) use (&$capturedCriteria) {
                $capturedCriteria = $criteria;
                return [];
            });

        $this->service->getAll(new User());

        $this->assertArrayNotHasKey(
            'visibility',
            $capturedCriteria,
            'Un utilisateur connecté voit toutes les ressources publiées (public + privé).'
        );
    }

    public function testGetAllReturnsResourcesFromRepository(): void
    {
        $r1 = new Resource();
        $r2 = new Resource();

        $this->repository->method('findBy')->willReturn([$r1, $r2]);

        $result = $this->service->getAll(null);

        $this->assertCount(2, $result);
    }

    // ----------------------------------------------------------------
    //  getOne()
    // ----------------------------------------------------------------

    public function testGetOneReturnsTheSameResource(): void
    {
        $resource = new Resource();
        $resource->setTitle('Ma ressource');

        $returned = $this->service->getOne($resource);

        $this->assertSame($resource, $returned);
    }

    // ----------------------------------------------------------------
    //  create()
    // ----------------------------------------------------------------

    public function testCreatePersistsAndFlushesNewResource(): void
    {
        $user = new User();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->create([
            'title'   => 'Titre',
            'content' => 'Contenu',
            'type'    => 'article',
        ], $user);
    }

    public function testCreateSetsTitleContentAndType(): void
    {
        $user = new User();

        $this->em->method('persist');
        $this->em->method('flush');

        $resource = $this->service->create([
            'title'   => 'Mon article',
            'content' => 'Corps du texte',
            'type'    => 'video',
        ], $user);

        $this->assertSame('Mon article', $resource->getTitle());
        $this->assertSame('Corps du texte', $resource->getContent());
        $this->assertSame('video', $resource->getType());
    }

    public function testCreateSetsStatusToPending(): void
    {
        $user = new User();

        $this->em->method('persist');
        $this->em->method('flush');

        $resource = $this->service->create([
            'title'   => 'Titre',
            'content' => 'Contenu',
            'type'    => 'article',
        ], $user);

        $this->assertSame(
            'pending',
            $resource->getStatus(),
            'Une ressource créée doit être en attente de modération.'
        );
    }

    public function testCreateSetsDefaultVisibilityToPublic(): void
    {
        $user = new User();

        $this->em->method('persist');
        $this->em->method('flush');

        $resource = $this->service->create([
            'title'   => 'Titre',
            'content' => 'Contenu',
            'type'    => 'article',
            // pas de 'visibility' → doit utiliser 'public' par défaut
        ], $user);

        $this->assertSame('public', $resource->getVisibility());
    }

    public function testCreateSetsVisibilityFromData(): void
    {
        $user = new User();

        $this->em->method('persist');
        $this->em->method('flush');

        $resource = $this->service->create([
            'title'      => 'Titre',
            'content'    => 'Contenu',
            'type'       => 'article',
            'visibility' => 'private',
        ], $user);

        $this->assertSame('private', $resource->getVisibility());
    }

    public function testCreateSetsAuthor(): void
    {
        $user = new User();
        $user->setEmail('auteur@example.com');

        $this->em->method('persist');
        $this->em->method('flush');

        $resource = $this->service->create([
            'title'   => 'Titre',
            'content' => 'Contenu',
            'type'    => 'article',
        ], $user);

        $this->assertSame($user, $resource->getAuthor());
    }

    public function testCreateReturnsResourceInstance(): void
    {
        $user = new User();

        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->create([
            'title'   => 'Titre',
            'content' => 'Contenu',
            'type'    => 'article',
        ], $user);

        $this->assertInstanceOf(Resource::class, $result);
    }

    public function testCreateThrowsWhenTitleMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'title' est requis");

        $this->service->create([
            'content' => 'Contenu',
            'type'    => 'article',
        ], new User());
    }

    public function testCreateThrowsWhenContentMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'content' est requis");

        $this->service->create([
            'title' => 'Titre',
            'type'  => 'article',
        ], new User());
    }

    public function testCreateThrowsWhenTypeMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le champ 'type' est requis");

        $this->service->create([
            'title'   => 'Titre',
            'content' => 'Contenu',
        ], new User());
    }

    public function testCreateWithCategoryIdCallsGetReference(): void
    {
        $user     = new User();
        $category = new \App\Entity\Category();

        $this->em->method('getReference')->willReturn($category);
        $this->em->method('persist');
        $this->em->method('flush');

        $resource = $this->service->create([
            'title'       => 'Titre',
            'content'     => 'Contenu',
            'type'        => 'article',
            'category_id' => 3,
        ], $user);

        $this->assertSame($category, $resource->getCategory());
    }

    // ----------------------------------------------------------------
    //  update()
    // ----------------------------------------------------------------

    public function testUpdateChangesTitle(): void
    {
        $resource = new Resource();
        $resource->setTitle('Ancien titre');
        $resource->setContent('Contenu');
        $resource->setStatus('published');

        $this->em->method('flush');

        $updated = $this->service->update($resource, ['title' => 'Nouveau titre']);

        $this->assertSame('Nouveau titre', $updated->getTitle());
    }

    public function testUpdateChangesContent(): void
    {
        $resource = new Resource();
        $resource->setTitle('Titre');
        $resource->setContent('Ancien contenu');
        $resource->setStatus('published');

        $this->em->method('flush');

        $updated = $this->service->update($resource, ['content' => 'Nouveau contenu']);

        $this->assertSame('Nouveau contenu', $updated->getContent());
    }

    public function testUpdateResetStatusToPendingAfterModification(): void
    {
        $resource = new Resource();
        $resource->setTitle('Titre');
        $resource->setContent('Contenu');
        $resource->setStatus('published');

        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->update($resource, ['title' => 'Titre modifié']);

        $this->assertSame(
            'pending',
            $updated->getStatus(),
            'Toute modification doit repasser la ressource en "pending" pour re-modération.'
        );
    }

    public function testUpdateSetsUpdatedAt(): void
    {
        $resource = new Resource();
        $resource->setTitle('Titre');
        $resource->setContent('Contenu');

        $this->em->method('flush');

        $before  = new \DateTimeImmutable();
        $updated = $this->service->update($resource, ['title' => 'Nouveau titre']);
        $after   = new \DateTimeImmutable();

        $updatedAt = $updated->getUpdatedAt();
        $this->assertNotNull($updatedAt);
        $this->assertGreaterThanOrEqual($before, $updatedAt);
        $this->assertLessThanOrEqual($after, $updatedAt);
    }

    public function testUpdateDoesNotChangeTitleWhenNotProvided(): void
    {
        $resource = new Resource();
        $resource->setTitle('Titre original');
        $resource->setContent('Contenu');

        $this->em->method('flush');

        $updated = $this->service->update($resource, []);

        $this->assertSame('Titre original', $updated->getTitle());
    }

    public function testUpdateCallsFlush(): void
    {
        $resource = new Resource();
        $resource->setTitle('Titre');
        $resource->setContent('Contenu');

        $this->em->expects($this->once())->method('flush');

        $this->service->update($resource, []);
    }

    public function testUpdateChangesVisibility(): void
    {
        $resource = new Resource();
        $resource->setTitle('Titre');
        $resource->setContent('Contenu');
        $resource->setVisibility('public');

        $this->em->method('flush');

        $updated = $this->service->update($resource, ['visibility' => 'private']);

        $this->assertSame('private', $updated->getVisibility());
    }

    // ----------------------------------------------------------------
    //  delete()
    // ----------------------------------------------------------------

    public function testDeleteCallsRemoveWithResource(): void
    {
        $resource = new Resource();

        $this->em->expects($this->once())->method('remove')->with($resource);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($resource);
    }

    public function testDeleteReturnsVoid(): void
    {
        $resource = new Resource();

        $this->em->method('remove');
        $this->em->method('flush');

        $result = $this->service->delete($resource);

        $this->assertNull($result);
    }
}
