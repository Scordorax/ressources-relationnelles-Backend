<?php

namespace App\Tests\Service\Category;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\Category\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CategoryService
 *
 * Couvre : getAll(), getOne(), create(), update(), delete()
 *
 * Le référentiel de catégories est administré exclusivement par
 * ROLE_ADMIN (cf. Tableau 2 du dossier Bloc 3). Le contrôle d'accès
 * lui-même est assuré par access_control côté pare-feu Symfony ;
 * ces tests portent sur la logique métier du service.
 */
class CategoryServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private CategoryRepository $repository;
    private CategoryService $service;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(CategoryRepository::class);

        $this->service = new CategoryService($this->em, $this->repository);
    }

    private function makeCategory(string $name, ?string $description = null): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setDescription($description);

        return $category;
    }

    // ----------------------------------------------------------------
    //  getAll()
    // ----------------------------------------------------------------

    public function testGetAllReturnsAllCategories(): void
    {
        $famille = $this->makeCategory('Famille');
        $couple  = $this->makeCategory('Couple');

        $this->repository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$famille, $couple]);

        $result = $this->service->getAll();

        $this->assertCount(2, $result);
        $this->assertSame($famille, $result[0]);
        $this->assertSame($couple, $result[1]);
    }

    public function testGetAllReturnsEmptyArrayWhenNoCategory(): void
    {
        $this->repository
            ->method('findAll')
            ->willReturn([]);

        $this->assertSame([], $this->service->getAll());
    }

    // ----------------------------------------------------------------
    //  getOne()
    // ----------------------------------------------------------------

    public function testGetOneReturnsTheGivenCategory(): void
    {
        $category = $this->makeCategory('Amis');

        $this->assertSame($category, $this->service->getOne($category));
    }

    // ----------------------------------------------------------------
    //  create()
    // ----------------------------------------------------------------

    public function testCreatePersistsAndFlushes(): void
    {
        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Category::class));

        $this->em->expects($this->once())->method('flush');

        $category = $this->service->create([
            'name'        => 'Collègues',
            'description' => 'Relations professionnelles',
        ]);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Collègues', $category->getName());
        $this->assertSame('Relations professionnelles', $category->getDescription());
    }

    public function testCreateAcceptsMissingDescription(): void
    {
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $category = $this->service->create(['name' => 'Voisinage']);

        $this->assertSame('Voisinage', $category->getName());
        $this->assertNull($category->getDescription());
    }

    public function testCreateSetsCreatedAtAutomatically(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $category = $this->service->create(['name' => 'Fratrie']);

        $this->assertInstanceOf(\DateTimeImmutable::class, $category->getCreatedAt());
    }

    public function testCreateThrowsWhenNameIsMissing(): void
    {
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est requis');

        $this->service->create(['description' => 'Sans nom']);
    }

    public function testCreateThrowsWhenNameIsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create(['name' => '']);
    }

    public function testCreateThrowsOnEmptyPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([]);
    }

    // ----------------------------------------------------------------
    //  update()
    // ----------------------------------------------------------------

    public function testUpdateChangesTheName(): void
    {
        $category = $this->makeCategory('Famille', 'Description initiale');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($category, ['name' => 'Famille élargie']);

        $this->assertSame('Famille élargie', $result->getName());
        $this->assertSame('Description initiale', $result->getDescription());
    }

    public function testUpdateChangesTheDescription(): void
    {
        $category = $this->makeCategory('Famille', 'Ancienne description');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($category, ['description' => 'Nouvelle description']);

        $this->assertSame('Famille', $result->getName());
        $this->assertSame('Nouvelle description', $result->getDescription());
    }

    public function testUpdateAllowsSettingDescriptionToNull(): void
    {
        $category = $this->makeCategory('Famille', 'Description à effacer');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($category, ['description' => null]);

        $this->assertNull($result->getDescription());
    }

    public function testUpdateIgnoresEmptyName(): void
    {
        $category = $this->makeCategory('Famille');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($category, ['name' => '']);

        $this->assertSame('Famille', $result->getName());
    }

    public function testUpdateWithEmptyPayloadLeavesEntityUnchanged(): void
    {
        $category = $this->makeCategory('Famille', 'Description');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($category, []);

        $this->assertSame('Famille', $result->getName());
        $this->assertSame('Description', $result->getDescription());
    }

    public function testUpdateChangesBothFieldsAtOnce(): void
    {
        $category = $this->makeCategory('Famille', 'Ancienne');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($category, [
            'name'        => 'Parentalité',
            'description' => 'Nouvelle',
        ]);

        $this->assertSame('Parentalité', $result->getName());
        $this->assertSame('Nouvelle', $result->getDescription());
    }

    // ----------------------------------------------------------------
    //  delete()
    // ----------------------------------------------------------------

    public function testDeleteRemovesAndFlushes(): void
    {
        $category = $this->makeCategory('Obsolète');

        $this->em->expects($this->once())
            ->method('remove')
            ->with($category);

        $this->em->expects($this->once())->method('flush');

        $this->service->delete($category);
    }
}
