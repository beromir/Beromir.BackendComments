<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Endpoint\Comments\RequestBody;

use Neos\Api\Endpoint\Nodes\Schema\DimensionSpacePoint;
use Neos\Api\Endpoint\Nodes\Schema\NodeAggregateId;
use Neos\Api\Endpoint\Workspaces\Schema\WorkspaceName;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A new comment on a node
 */
final readonly class CommentCreate implements ProvidesSchema
{
    /**
     * @param NodeAggregateId $nodeAggregateId the node to comment on
     * @param string $text the comment, not empty
     * @param WorkspaceName|null $workspaceName the workspace the node is seen in, live if omitted
     * @param DimensionSpacePoint|null $dimensionSpacePoint the dimension space point the node is seen in, the default one if omitted
     * @param string|null $propertyName one of the node type's properties, to comment on that property only
     */
    public function __construct(
        public NodeAggregateId $nodeAggregateId,
        public string $text,
        public ?WorkspaceName $workspaceName = null,
        public ?DimensionSpacePoint $dimensionSpacePoint = null,
        public ?string $propertyName = null,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
