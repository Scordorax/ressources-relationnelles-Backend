<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Resource;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Resource
 *
 * Le statut par défaut « pending » matérialise le workflow de
 * modération décrit au § 2.2 du dossier (risque R5 : publication de
 * contenu illicite). Une ressource ne doit jamais être publiée sans
 * passage explicite par un modérateur.
 */
class ResourceTest extends TestCase
{
    // ----------------------------------------------------------------
    //  Valeurs par défaut — workflow de modération
    // ----------------------------------------------------------------

    public function testNewResourceIsPendingByDefault(): void
    {
        $resource = new Resource();

        $this->assertSame('pending', $resource->getStatus());
    }

    public function testNewResourceIsPublicByDefault(): void
    {
        $this->assertSame('public', (new Resource())->getVisibility());
    }

    public function testNewResourceHasCreationTimestamp(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, (new Resource())->getCreatedAt());
    }

    public function testNewResourceHasNoUpdateTimestamp(): void
    {
        $this->assertNull((new Resource())->getUpdatedAt());
    }

    public function testNewResourceHasEmptyCollections(): void
    {
        $resource = new Resource();

        $this->assertCount(0, $resource->getComments());
        $this->assertCount(0, $resource->getResourceInteractions());
        $this->assertCount(0, $resource->getStatistics());
    }

    // ----------------------------------------------------------------
    //  Transitions de statut
    // ----------------------------------------------------------------

    public function testResourceCanBePublished(): void
    {
        $resource = new Resource();
        $resource->setStatus('published');

        $this->assertSame('published', $resource->getStatus());
    }

    public function testResourceCanBeRejected(): void
    {
        $resource = new Resource();
        $resource->setStatus('rejected');

        $this->assertSame('rejected', $resource->getStatus());
    }

    public function testRejectedResourceCanReturnToPending(): void
    {
        $resource = new Resource();
        $resource->setStatus('rejected');
        $resource->setStatus('pending');

        $this->assertSame('pending', $resource->getStatus());
    }

    // ----------------------------------------------------------------
    //  Visibilité
    // ----------------------------------------------------------------

    public function testResourceCanBeSetPrivate(): void
    {
        $resource = new Resource();
        $resource->setVisibility('private');

        $this->assertSame('private', $resource->getVisibility());
    }

    // ----------------------------------------------------------------
    //  Contenu et accesseurs
    // ----------------------------------------------------------------

    public function testTitleAndContentAreStored(): void
    {
        $resource = new Resource();
        $resource->setTitle('Renforcer le lien parent-enfant');
        $resource->setContent('Contenu détaillé de la ressource.');

        $this->assertSame('Renforcer le lien parent-enfant', $resource->getTitle());
        $this->assertSame('Contenu détaillé de la ressource.', $resource->getContent());
    }

    /**
     * @dataProvider resourceTypeProvider
     */
    public function testEachResourceTypeIsAccepted(string $type): void
    {
        $resource = new Resource();
        $resource->setType($type);

        $this->assertSame($type, $resource->getType());
    }

    public static function resourceTypeProvider(): array
    {
        return [
            'article'  => ['article'],
            'vidéo'    => ['video'],
            'guide'    => ['guide'],
            'activité' => ['activity'],
        ];
    }

    public function testAccessorsAreFluent(): void
    {
        $resource = new Resource();

        $result = $resource
            ->setTitle('Titre')
            ->setContent('Contenu')
            ->setType('article');

        $this->assertSame($resource, $result);
    }

    // ----------------------------------------------------------------
    //  Relations
    // ----------------------------------------------------------------

    public function testAuthorCanBeAssigned(): void
    {
        $author = new User();
        $author->setEmail('auteur@example.fr');

        $resource = new Resource();
        $resource->setAuthor($author);

        $this->assertSame($author, $resource->getAuthor());
    }

    public function testAuthorCanBeDetachedForAnonymisation(): void
    {
        // Droit à l'effacement : l'auteur est dissocié, le contenu
        // d'intérêt public est conservé (§ 2.4.3 du dossier).
        $resource = new Resource();
        $resource->setAuthor(new User());
        $resource->setAuthor(null);

        $this->assertNull($resource->getAuthor());
    }

    public function testCategoryCanBeAssignedAndRemoved(): void
    {
        $category = new Category();
        $category->setName('Famille');

        $resource = new Resource();
        $resource->setCategory($category);
        $this->assertSame($category, $resource->getCategory());

        $resource->setCategory(null);
        $this->assertNull($resource->getCategory());
    }

    // ----------------------------------------------------------------
    //  Collection de commentaires
    // ----------------------------------------------------------------

    public function testAddCommentRegistersTheComment(): void
    {
        $resource = new Resource();
        $comment  = new Comment();

        $resource->addComment($comment);

        $this->assertCount(1, $resource->getComments());
        $this->assertTrue($resource->getComments()->contains($comment));
    }

    public function testAddCommentSetsTheOwningSide(): void
    {
        $resource = new Resource();
        $comment  = new Comment();

        $resource->addComment($comment);

        $this->assertSame($resource, $comment->getResource());
    }

    public function testAddCommentTwiceDoesNotDuplicate(): void
    {
        $resource = new Resource();
        $comment  = new Comment();

        $resource->addComment($comment);
        $resource->addComment($comment);

        $this->assertCount(1, $resource->getComments());
    }

    public function testRemoveCommentDetachesIt(): void
    {
        $resource = new Resource();
        $comment  = new Comment();

        $resource->addComment($comment);
        $resource->removeComment($comment);

        $this->assertCount(0, $resource->getComments());
    }

    public function testRemoveUnknownCommentIsSafe(): void
    {
        $resource = new Resource();

        $resource->removeComment(new Comment());

        $this->assertCount(0, $resource->getComments());
    }

    // ----------------------------------------------------------------
    //  Horodatage de modification
    // ----------------------------------------------------------------

    public function testUpdatedAtCanBeSet(): void
    {
        $date     = new \DateTimeImmutable('2026-06-01 12:00:00');
        $resource = new Resource();
        $resource->setUpdatedAt($date);

        $this->assertSame($date, $resource->getUpdatedAt());
    }

    public function testCreatedAtCanBeOverridden(): void
    {
        $date     = new \DateTimeImmutable('2026-01-01 00:00:00');
        $resource = new Resource();
        $resource->setCreatedAt($date);

        $this->assertSame($date, $resource->getCreatedAt());
    }
}
