<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookStatus: string
{
    case RECEIVED = 'received';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case IGNORED = 'ignored';
}

