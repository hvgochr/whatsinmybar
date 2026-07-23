<?php

namespace App\Enum;

enum RecipeModerationStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
    case PendingReview = 'pending_review';
    case Removed = 'removed';

    public function isPubliclyReadable(): bool
    {
        return self::Visible === $this;
    }
}
