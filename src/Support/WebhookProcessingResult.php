<?php

declare(strict_types=1);

namespace Capell\Api\Support;

enum WebhookProcessingResult: string
{
    case Invalid = 'invalid';
    case Busy = 'busy';
    case Duplicate = 'duplicate';
    case Processed = 'processed';
}
