<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Database = 'database';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Push = 'push';
    case WebSocket = 'websocket';
}
