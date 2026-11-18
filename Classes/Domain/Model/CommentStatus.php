<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Domain\Model;

enum CommentStatus: string
{
    case OPEN = 'OPEN';
    case RESOLVED = 'RESOLVED';
}
