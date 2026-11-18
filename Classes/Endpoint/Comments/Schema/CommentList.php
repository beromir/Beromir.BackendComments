<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Endpoint\Comments\Schema;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A list of comments
 *
 * @implements \IteratorAggregate<Comment>
 */
final readonly class CommentList implements ProvidesSchema, \IteratorAggregate
{
    /**
     * @var list<Comment>
     */
    public array $comments;

    public function __construct(Comment ...$comments)
    {
        $this->comments = array_values($comments);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->comments;
    }
}
