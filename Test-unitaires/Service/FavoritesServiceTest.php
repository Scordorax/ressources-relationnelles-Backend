<?php

namespace App\Tests\Service\Favorites;

use App\Entity\User;
use App\Repository\ResourceInteractionRepository;
use App\Repository\ResourceRepository;
use App\Service\Favorites\FavoritesService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour FavoritesService
 *
 * Couvre : getFavoritesByUser(), getByUserAndType(),
 *          getByStatusAndUser(), getByStatus()
 *
 * Ce service alimente le tableau de bord de progression du citoyen
 * (favoris, ressources exploitées, ressources mises de côté) ainsi que
 * la file de modération. Les tests vérifient en particulier que le
 * filtrage par utilisateur est bien appliqué : une fuite sur ce point
 * exposerait les ressources privées d'un citoyen à un autre (risque R4).
 */
class FavoritesServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ResourceInteractionRepository $interactionRepository;
    private ResourceRepository $resourceRepository;
    private FavoritesService $service;

    protected function setUp(): void
    {
        $this->em                    = $this->createMock(EntityManagerInterface::class);
        $this->interactionRepository = $this->createMock(ResourceInteractionRepository::class);
        $this->resourceRepository    = $this->createMock(ResourceRepository::class);

        $this->service = new FavoritesService(
            $this->em,
            $this->interactionRepository,
            $this->resourceRepository
        );
    }

    // ----------------------------------------------------------------
    //  getFavoritesByUser()
    // ----------------------------------------------------------------

    public function testGetFavoritesByUserFiltersOnUserAndFavoriteType(): void
    {
        $user = new User();

        $this->interactionRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['user' => $user, 'type' => 'favorite'])
            ->willReturn(['interaction-1', 'interaction-2']);

        $result = $this->service->getFavoritesByUser($user);

        $this->assertCount(2, $result);
    }

    public function testGetFavoritesByUserReturnsEmptyArrayWhenNoFavorite(): void
    {
        $user = new User();

        $this->interactionRepository
            ->method('findBy')
            ->willReturn([]);

        $this->assertSame([], $this->service->getFavoritesByUser($user));
    }

    public function testGetFavoritesByUserNeverReturnsAnotherUsersData(): void
    {
        $marie = new User();
        $paul  = new User();

        // Le service doit filtrer strictement sur l'utilisateur transmis.
        $this->interactionRepository
            ->expects($this->once())
            ->method('findBy')
            ->with($this->callback(
                fn (array $criteria) => $criteria['user'] === $marie
                    && $criteria['user'] !== $paul
            ))
            ->willReturn([]);

        $this->service->getFavoritesByUser($marie);
    }

    // ----------------------------------------------------------------
    //  getByUserAndType()
    // ----------------------------------------------------------------

    /**
     * @dataProvider interactionTypeProvider
     */
    public function testGetByUserAndTypeFiltersOnTheGivenType(string $type): void
    {
        $user = new User();

        $this->interactionRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['user' => $user, 'type' => $type])
            ->willReturn([]);

        $this->service->getByUserAndType($user, $type);
    }

    public static function interactionTypeProvider(): array
    {
        return [
            'favori'          => ['favorite'],
            'exploitée'       => ['progress'],
            'mise de côté'    => ['aside'],
            'partagée'        => ['share'],
        ];
    }

    public function testGetByUserAndTypeReturnsRepositoryResult(): void
    {
        $user = new User();

        $this->interactionRepository
            ->method('findBy')
            ->willReturn(['a', 'b', 'c']);

        $this->assertCount(3, $this->service->getByUserAndType($user, 'aside'));
    }

    // ----------------------------------------------------------------
    //  getByStatusAndUser()
    // ----------------------------------------------------------------

    public function testGetByStatusAndUserFiltersOnAuthorAndStatus(): void
    {
        $user = new User();

        $this->resourceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                ['author' => $user, 'status' => 'pending'],
                ['createdAt' => 'DESC']
            )
            ->willReturn([]);

        $this->service->getByStatusAndUser($user, 'pending');
    }

    public function testGetByStatusAndUserSortsByCreationDateDescending(): void
    {
        $user = new User();

        $this->resourceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->anything(),
                $this->equalTo(['createdAt' => 'DESC'])
            )
            ->willReturn([]);

        $this->service->getByStatusAndUser($user, 'draft');
    }

    /**
     * @dataProvider resourceStatusProvider
     */
    public function testGetByStatusAndUserAcceptsEachStatus(string $status): void
    {
        $user = new User();

        $this->resourceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['author' => $user, 'status' => $status], ['createdAt' => 'DESC'])
            ->willReturn([]);

        $this->service->getByStatusAndUser($user, $status);
    }

    public static function resourceStatusProvider(): array
    {
        return [
            'brouillon' => ['draft'],
            'en attente' => ['pending'],
            'publiée'   => ['published'],
            'rejetée'   => ['rejected'],
        ];
    }

    // ----------------------------------------------------------------
    //  getByStatus()
    // ----------------------------------------------------------------

    public function testGetByStatusDoesNotFilterOnAuthor(): void
    {
        // Cette méthode alimente la file de modération : elle doit
        // retourner les ressources de tous les auteurs.
        $this->resourceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                $this->callback(
                    fn (array $criteria) => !array_key_exists('author', $criteria)
                        && $criteria['status'] === 'pending'
                ),
                ['createdAt' => 'DESC']
            )
            ->willReturn([]);

        $this->service->getByStatus('pending');
    }

    public function testGetByStatusSortsByCreationDateDescending(): void
    {
        $this->resourceRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                ['status' => 'published'],
                ['createdAt' => 'DESC']
            )
            ->willReturn([]);

        $this->service->getByStatus('published');
    }

    public function testGetByStatusReturnsEmptyArrayWhenNoMatch(): void
    {
        $this->resourceRepository
            ->method('findBy')
            ->willReturn([]);

        $this->assertSame([], $this->service->getByStatus('rejected'));
    }
}
