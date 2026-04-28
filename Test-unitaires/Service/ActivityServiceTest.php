<?php

namespace App\Tests\Service\Activity;

use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\Activity\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ActivityService
 *
 * Couvre : getAll(), start()
 *
 * NOTE : start() ne persiste aucune participation.
 * La logique est marquée "ex : enregistrement de participation" mais n'est
 * pas implémentée. Voir comments.md, problème #10.
 */
class ActivityServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ActivityRepository $repository;
    private ActivityService $service;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(ActivityRepository::class);

        $this->service = new ActivityService($this->em, $this->repository);
    }

    // ----------------------------------------------------------------
    //  getAll()
    // ----------------------------------------------------------------

    public function testGetAllCallsFindAll(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->service->getAll();
    }

    public function testGetAllReturnsActivitiesFromRepository(): void
    {
        $a1 = new Activity();
        $a2 = new Activity();

        $this->repository->method('findAll')->willReturn([$a1, $a2]);

        $result = $this->service->getAll();

        $this->assertCount(2, $result);
        $this->assertSame($a1, $result[0]);
        $this->assertSame($a2, $result[1]);
    }

    public function testGetAllReturnsEmptyArrayWhenNone(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $result = $this->service->getAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    //  start()
    // ----------------------------------------------------------------

    public function testStartReturnsMessageKey(): void
    {
        $activity = $this->createMock(Activity::class);
        $activity->method('getId')->willReturn(1);
        $activity->method('getName')->willReturn('Atelier bien-être');

        $this->repository->method('find')->with(1)->willReturn($activity);

        $result = $this->service->start(1, new User());

        $this->assertArrayHasKey('message', $result);
    }

    public function testStartReturnsActivityKey(): void
    {
        $activity = $this->createMock(Activity::class);
        $activity->method('getId')->willReturn(1);
        $activity->method('getName')->willReturn('Atelier bien-être');

        $this->repository->method('find')->willReturn($activity);

        $result = $this->service->start(1, new User());

        $this->assertArrayHasKey('activity', $result);
    }

    public function testStartReturnsCorrectActivityDemarree(): void
    {
        $activity = $this->createMock(Activity::class);
        $activity->method('getId')->willReturn(5);
        $activity->method('getName')->willReturn('Yoga matinal');

        $this->repository->method('find')->with(5)->willReturn($activity);

        $result = $this->service->start(5, new User());

        $this->assertSame('Activité démarrée', $result['message']);
    }

    public function testStartReturnsActivityIdAndName(): void
    {
        $activity = $this->createMock(Activity::class);
        $activity->method('getId')->willReturn(7);
        $activity->method('getName')->willReturn('Méditation guidée');

        $this->repository->method('find')->with(7)->willReturn($activity);

        $result = $this->service->start(7, new User());

        $this->assertSame(7, $result['activity']['id']);
        $this->assertSame('Méditation guidée', $result['activity']['name']);
    }

    public function testStartThrowsWhenActivityNotFound(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Activité introuvable');

        $this->service->start(999, new User());
    }

    public function testStartDoesNotPersistAnything(): void
    {
        // Documente que start() ne persiste PAS de participation (fonctionnalité incomplète)
        $activity = $this->createMock(Activity::class);
        $activity->method('getId')->willReturn(1);
        $activity->method('getName')->willReturn('Test');

        $this->repository->method('find')->willReturn($activity);

        // Aucun persist ni flush ne doit être appelé
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->service->start(1, new User());
    }
}
