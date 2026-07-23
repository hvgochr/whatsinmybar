<?php

namespace App\Enum;

enum ReportReason: string
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case IllegalContent = 'illegal_content';
    case WrongAlcoholClassification = 'wrong_alcohol_classification';
    case Copyright = 'copyright';
    case Other = 'other';
}
