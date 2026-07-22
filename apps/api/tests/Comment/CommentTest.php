<?php

namespace App\Tests\Comment;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\User;
use App\Enum\CommentModerationStatus;
use PHPUnit\Framework\TestCase;

final class CommentTest extends TestCase
{
    public function testMessageIsTrimmed(): void
    {
        $comment = new Comment($this->recipe(), $this->user());

        $comment->setMessage('  Great cocktail.  ');

        self::assertSame('Great cocktail.', $comment->getMessage());
        self::assertSame('Great cocktail.', $comment->getPublicMessage());
    }

    public function testParentMustBelongToSameRecipe(): void
    {
        $comment = new Comment($this->recipe(), $this->user());
        $parent = new Comment($this->recipe(), $this->user());

        $this->expectException(\InvalidArgumentException::class);

        $comment->setParent($parent);
    }

    public function testSoftDeletedCommentDoesNotExposeMessage(): void
    {
        $comment = new Comment($this->recipe(), $this->user());
        $comment->setMessage('Original message');

        $comment->softDelete();

        self::assertNull($comment->getPublicMessage());
        self::assertSame(CommentModerationStatus::Removed, $comment->getModerationStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $comment->getDeletedAt());
    }

    public function testHiddenCommentDoesNotExposeMessage(): void
    {
        $comment = new Comment($this->recipe(), $this->user());
        $comment->setMessage('Original message');

        $comment->setModerationStatus(CommentModerationStatus::Hidden);

        self::assertNull($comment->getPublicMessage());
    }

    private function recipe(): Recipe
    {
        return new Recipe();
    }

    private function user(): User
    {
        return new User('comment@example.com', 'comment_user', new \DateTimeImmutable('1990-01-01'));
    }
}
