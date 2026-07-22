<?php

namespace App\Enum;

enum CommentModerationStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
    case PendingReview = 'pending_review';
    case Removed = 'removed';
}
