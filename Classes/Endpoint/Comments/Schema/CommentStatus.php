<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Endpoint\Comments\Schema;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;

/**
 * The values of Beromir\BackendComments\Domain\Model\CommentStatus
 */
enum CommentStatus: string implements ProvidesSchema
{
    case OPEN = 'OPEN';
    case RESOLVED = 'RESOLVED';

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= StringSchema::create(
            description: 'OPEN while the comment waits to be dealt with, RESOLVED once it was',
            enum: array_map(static fn (self $case) => $case->value, self::cases()),
        );
    }
}
