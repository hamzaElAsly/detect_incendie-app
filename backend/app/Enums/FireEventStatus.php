<?php

namespace App\Enums;

enum FireEventStatus: string
{
    case Detected = 'detected';
    case Confirmed = 'confirmed';
    case FalseAlarm = 'false_alarm';
    case Resolved = 'resolved';
}
