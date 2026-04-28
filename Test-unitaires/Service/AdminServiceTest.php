<?php

namespace App\Tests\Service\Admin;

use App\Entity\Category;
use App\Entity\Resource;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ResourceRepository;
use App\Repository\UserRepository;
use App\Service\Admin\AdminService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AdminService
 *
 * Couvre : getAllResources(), deleteResource(),
 *          getAllCategories(), createCategory(), updateCategory(), deleteCategory(),
 *          getAllUsers(), deleteUser(), banUser(), getStatistics(), exportStatistics()
 *
 * NOTE (banUser) : le bannissement se fait via setIsVerified(false).
 * Le JwtAuthenticator ne vérifie PAS ce flag → un utilisateur banni
 * avec un JWT valide peut continuer à appeler l'API. Voir comments.md, problème #6.
 */
class AdminServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ResourceRepository $resourceRepository;
    private CategoryRepository $categoryRepository;
    private UserRepository $userRepository;
    private AdminService $service;

    protected function setUp(): void
    {
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->resourceRepository = $this->createMock(ResourceRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->userRepository     = $this->createMock(UserRepository::class);

        $this->service = new AdminService(
            $this->em,
            $this->resourceRepository,
            $this->categoryRepository,
            $this->userRepository
        );
    }

    // ----------------------------------------------------------------
    //  Ressources
    // ----------------------------------------------------------------

    public function testGetAllResourcesReturnsRepositoryResult(): void
    {
        $r1 = new Resource();
        $r2 = new Resource();

        $this->resourceRepository->method('findAll')->willReturn([$r1, $r2]);

        $result = $this->service->getAllResources();

        $this->assertCount(2, $result);
        $this->assertSame($r1, $result[0]);
        $this->assertSame($r2, $result[1]);
    }

    public function testGetAllResourcesReturnsEmptyArrayWhenNoneExist(): void
    {
        $this->resourceRepository->method('findAll')->willReturn([]);

        $result = $this->service->getAllResources();

        $this->assertSame([], $result);
    }

    public function testDeleteResourceRemovesAndFlushes(): void
    {
        $resource = new Resource();

        $this->resourceRepository->method('find')->with(1)->willReturn($resource);
        $this->em->expects($this->once())->method('remove')->with($resource);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteResource(1);
    }

    public function testDeleteResourceThrowsWhenResourceNotFound(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ressource introuvable');

        $this->service->deleteResource(999);
    }

    // ----------------------------------------------------------------
    //  Catégories
    // ----------------------------------------------------------------

    public function testGetAllCategoriesReturnsRepositoryResult(): void
    {
        $c = new Category();
        $this->categoryRepository->method('findAll')->willReturn([$c]);

        $result = $this->service->getAllCategories();

        $this->assertCount(1, $result);
    }

    public function testCreateCategoryPersistsAndFlushes(): void
    {
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->createCategory(['name' => 'Santé', 'description' => 'Catégorie santé']);
    }

    public function testCreateCategoryReturnsCategoryWithName(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $category = $this->service->createCategory(['name' => 'Sport', 'description' => null]);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Sport', $category->getName());
    }

    public function testCreateCategoryWithDescription(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $category = $this->service->createCategory([
            'name'        => 'Santé',
            'description' => 'Ressources de santé mentale',
        ]);

        $this->assertSame('Ressources de santé mentale', $category->getDescription());
    }

    public function testCreateCategoryWithoutDescriptionSetsNull(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $category = $this->service->createCategory(['name' => 'Famille']);

        $this->assertNull($category->getDescription());
    }

    public function testUpdateCategoryChangesName(): void
    {
        $category = new Category();
        $category->setName('Ancien nom');

        $this->categoryRepository->method('find')->with(1)->willReturn($category);
        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->updateCategory(1, ['name' => 'Nouveau nom']);

        $this->assertSame('Nouveau nom', $updated->getName());
    }

    public function testUpdateCategoryChangesDescription(): void
    {
        $category = new Category();
        $category->setName('Nom');
        $category->setDescription('Ancienne description');

        $this->categoryRepository->method('find')->willReturn($category);
        $this->em->method('flush');

        $updated = $this->service->updateCategory(1, ['description' => 'Nouvelle description']);

        $this->assertSame('Nouvelle description', $updated->getDescription());
    }

    public function testUpdateCategoryCanSetDescriptionToNull(): void
    {
        $category = new Category();
        $category->setName('Nom');
        $category->setDescription('Ancienne description');

        $this->categoryRepository->method('find')->willReturn($category);
        $this->em->method('flush');

        $updated = $this->service->updateCategory(1, ['description' => null]);

        $this->assertNull($updated->getDescription());
    }

    public function testUpdateCategoryDoesNotChangeMissingName(): void
    {
        $category = new Category();
        $category->setName('Nom original');

        $this->categoryRepository->method('find')->willReturn($category);
        $this->em->method('flush');

        $updated = $this->service->updateCategory(1, []);

        $this->assertSame('Nom original', $updated->getName());
    }

    public function testUpdateCategoryThrowsWhenNotFound(): void
    {
        $this->categoryRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Catégorie introuvable');

        $this->service->updateCategory(999, ['name' => 'Nouveau']);
    }

    public function testDeleteCategoryRemovesAndFlushes(): void
    {
        $category = new Category();
        $category->setName('À supprimer');

        $this->categoryRepository->method('find')->with(5)->willReturn($category);
        $this->em->expects($this->once())->method('remove')->with($category);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteCategory(5);
    }

    public function testDeleteCategoryThrowsWhenNotFound(): void
    {
        $this->categoryRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Catégorie introuvable');

        $this->service->deleteCategory(999);
    }

    // ----------------------------------------------------------------
    //  Utilisateurs
    // ----------------------------------------------------------------

    public function testGetAllUsersReturnsRepositoryResult(): void
    {
        $u1 = new User();
        $u2 = new User();

        $this->userRepository->method('findAll')->willReturn([$u1, $u2]);

        $result = $this->service->getAllUsers();

        $this->assertCount(2, $result);
    }

    public function testDeleteUserRemovesAndFlushes(): void
    {
        $user = new User();

        $this->userRepository->method('find')->with(7)->willReturn($user);
        $this->em->expects($this->once())->method('remove')->with($user);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteUser(7);
    }

    public function testDeleteUserThrowsWhenNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Utilisateur introuvable');

        $this->service->deleteUser(999);
    }

    public function testBanUserSetsIsVerifiedToFalse(): void
    {
        $user = new User();
        $user->setIsVerified(true);

        $this->userRepository->method('find')->with(3)->willReturn($user);
        $this->em->expects($this->once())->method('flush');

        $this->service->banUser(3);

        $this->assertFalse(
            $user->isVerified(),
            'banUser() doit désactiver le compte via setIsVerified(false).'
        );
    }

    public function testBanUserThrowsWhenNotFound(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Utilisateur introuvable');

        $this->service->banUser(999);
    }

    // ----------------------------------------------------------------
    //  Statistiques
    // ----------------------------------------------------------------

    public function testGetStatisticsReturnsAllRequiredKeys(): void
    {
        $this->resourceRepository->method('count')->willReturnMap([
            [[], 15],
            [['status' => 'pending'], 4],
            [['status' => 'published'], 11],
        ]);
        $this->userRepository->method('count')->willReturn(8);

        $stats = $this->service->getStatistics();

        $this->assertArrayHasKey('total_resources', $stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('published', $stats);
    }

    public function testGetStatisticsReturnsCorrectCounts(): void
    {
        $this->resourceRepository->method('count')->willReturnMap([
            [[], 15],
            [['status' => 'pending'], 4],
            [['status' => 'published'], 11],
        ]);
        $this->userRepository->method('count')->willReturn(8);

        $stats = $this->service->getStatistics();

        $this->assertSame(15, $stats['total_resources']);
        $this->assertSame(8, $stats['total_users']);
        $this->assertSame(4, $stats['pending']);
        $this->assertSame(11, $stats['published']);
    }

    public function testExportStatisticsReturnsSameAsGetStatistics(): void
    {
        $this->resourceRepository->method('count')->willReturnMap([
            [[], 10],
            [['status' => 'pending'], 2],
            [['status' => 'published'], 8],
        ]);
        $this->userRepository->method('count')->willReturn(5);

        $stats  = $this->service->getStatistics();
        $export = $this->service->exportStatistics();

        $this->assertSame($stats, $export);
    }
}
