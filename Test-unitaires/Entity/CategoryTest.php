<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Resource;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Category
 *
 * Le référentiel de catégories structure le catalogue de ressources.
 * Il est administré par ROLE_ADMIN via CategoryService.
 */
class CategoryTest extends TestCase
{
    public function testNewCategoryHasCreationTimestamp(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, (new Category())->getCreatedAt());
    }

    public function testNewCategoryHasNoIdBeforePersistence(): void
    {
        $this->assertNull((new Category())->getId());
    }

    public function testNewCategoryHasNoResource(): void
    {
        $this->assertCount(0, (new Category())->getResources());
    }

    public function testNameIsStored(): void
    {
        $category = new Category();
        $category->setName('Famille');

        $this->assertSame('Famille', $category->getName());
    }

    public function testDescriptionIsOptional(): void
    {
        $this->assertNull((new Category())->getDescription());
    }

    public function testDescriptionCanBeSetAndCleared(): void
    {
        $category = new Category();
        $category->setDescription('Relations familiales');
        $this->assertSame('Relations familiales', $category->getDescription());

        $category->setDescription(null);
        $this->assertNull($category->getDescription());
    }

    public function testAccessorsAreFluent(): void
    {
        $category = new Category();

        $result = $category->setName('Couple')->setDescription('Vie de couple');

        $this->assertSame($category, $result);
    }

    // ----------------------------------------------------------------
    //  Relation avec les ressources
    // ----------------------------------------------------------------

    public function testAddResourceRegistersIt(): void
    {
        $category = new Category();
        $resource = new Resource();

        $category->addResource($resource);

        $this->assertCount(1, $category->getResources());
        $this->assertTrue($category->getResources()->contains($resource));
    }

    public function testAddResourceSetsTheOwningSide(): void
    {
        $category = new Category();
        $resource = new Resource();

        $category->addResource($resource);

        $this->assertSame($category, $resource->getCategory());
    }

    public function testAddResourceTwiceDoesNotDuplicate(): void
    {
        $category = new Category();
        $resource = new Resource();

        $category->addResource($resource);
        $category->addResource($resource);

        $this->assertCount(1, $category->getResources());
    }

    public function testSeveralResourcesCanBeAttached(): void
    {
        $category = new Category();

        $category->addResource(new Resource());
        $category->addResource(new Resource());

        $this->assertCount(2, $category->getResources());
    }
}
