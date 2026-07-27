<?php

namespace App\Tests\Entity;

use App\Entity\Comment;
use App\Entity\Resource;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Comment
 *
 * Le drapeau isReported alimente la file de modération des échanges
 * (§ 4.2 du dossier). Les réponses imbriquées matérialisent les fils
 * de discussion attachés aux ressources publiques.
 */
class CommentTest extends TestCase
{
    // ----------------------------------------------------------------
    //  Valeurs par défaut
    // ----------------------------------------------------------------

    public function testNewCommentIsNotReported(): void
    {
        $this->assertFalse((new Comment())->isReported());
    }

    public function testNewCommentHasCreationTimestamp(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, (new Comment())->getCreatedAt());
    }

    public function testNewCommentHasNoParent(): void
    {
        $this->assertNull((new Comment())->getParent());
    }

    public function testNewCommentHasNoReplies(): void
    {
        $this->assertCount(0, (new Comment())->getReplies());
    }

    public function testNewCommentHasNoIdBeforePersistence(): void
    {
        $this->assertNull((new Comment())->getId());
    }

    // ----------------------------------------------------------------
    //  Contenu
    // ----------------------------------------------------------------

    public function testContentIsStored(): void
    {
        $comment = new Comment();
        $comment->setContent('Merci pour cette ressource.');

        $this->assertSame('Merci pour cette ressource.', $comment->getContent());
    }

    public function testAccessorsAreFluent(): void
    {
        $comment = new Comment();

        $result = $comment->setContent('Texte')->setIsReported(true);

        $this->assertSame($comment, $result);
    }

    // ----------------------------------------------------------------
    //  Signalement et modération
    // ----------------------------------------------------------------

    public function testCommentCanBeReported(): void
    {
        $comment = new Comment();
        $comment->setIsReported(true);

        $this->assertTrue($comment->isReported());
    }

    public function testReportCanBeLiftedByModerator(): void
    {
        $comment = new Comment();
        $comment->setIsReported(true);
        $comment->setIsReported(false);

        $this->assertFalse($comment->isReported());
    }

    // ----------------------------------------------------------------
    //  Relations
    // ----------------------------------------------------------------

    public function testAuthorCanBeAssigned(): void
    {
        $user = new User();
        $user->setEmail('citoyen@example.fr');

        $comment = new Comment();
        $comment->setUser($user);

        $this->assertSame($user, $comment->getUser());
    }

    public function testAuthorCanBeDetachedForAnonymisation(): void
    {
        // Droit à l'effacement : le fil de discussion reste cohérent
        // mais l'identité de l'auteur n'est plus conservée.
        $comment = new Comment();
        $comment->setUser(new User());
        $comment->setUser(null);

        $this->assertNull($comment->getUser());
    }

    public function testResourceCanBeAssigned(): void
    {
        $resource = new Resource();
        $comment  = new Comment();

        $comment->setResource($resource);

        $this->assertSame($resource, $comment->getResource());
    }

    // ----------------------------------------------------------------
    //  Fils de discussion imbriqués
    // ----------------------------------------------------------------

    public function testAddReplyRegistersTheReply(): void
    {
        $parent = new Comment();
        $reply  = new Comment();

        $parent->addReply($reply);

        $this->assertCount(1, $parent->getReplies());
        $this->assertTrue($parent->getReplies()->contains($reply));
    }

    public function testAddReplySetsTheParentOnTheOwningSide(): void
    {
        $parent = new Comment();
        $reply  = new Comment();

        $parent->addReply($reply);

        $this->assertSame($parent, $reply->getParent());
    }

    public function testAddReplyTwiceDoesNotDuplicate(): void
    {
        $parent = new Comment();
        $reply  = new Comment();

        $parent->addReply($reply);
        $parent->addReply($reply);

        $this->assertCount(1, $parent->getReplies());
    }

    public function testRemoveReplyDetachesIt(): void
    {
        $parent = new Comment();
        $reply  = new Comment();

        $parent->addReply($reply);
        $parent->removeReply($reply);

        $this->assertCount(0, $parent->getReplies());
    }

    public function testRemoveUnknownReplyIsSafe(): void
    {
        $parent = new Comment();

        $parent->removeReply(new Comment());

        $this->assertCount(0, $parent->getReplies());
    }

    public function testSeveralRepliesCanBeAttached(): void
    {
        $parent = new Comment();

        $parent->addReply(new Comment());
        $parent->addReply(new Comment());
        $parent->addReply(new Comment());

        $this->assertCount(3, $parent->getReplies());
    }
}
