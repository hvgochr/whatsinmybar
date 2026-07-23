<?php

namespace App\Enum;

enum ReportTargetType: string
{
    case Recipe = 'recipe';
    case Comment = 'comment';
    case User = 'user';
}
