<?php

namespace App\Enum;

enum ReportStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}
