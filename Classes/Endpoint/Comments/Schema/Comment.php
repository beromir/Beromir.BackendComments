<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Endpoint\Comments\Schema;

use Neos\Api\Endpoint\Nodes\Schema\DimensionSpacePoint;
use Neos\Api\Endpoint\Nodes\Schema\NodeAggregateId;
use Neos\Api\Endpoint\NodeTypes\Schema\NodeTypeName;
use Neos\Api\Endpoint\Workspaces\Schema\WorkspaceName;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A comment on a node
 */
final readonly class Comment implements ProvidesSchema
{
    /**
     * @param NodeAggregateId $nodeAggregateId the node the comment is about
     * @param NodeAggregateId|null $documentNodeAggregateId the document (page) the node is on, the node itself for a document
     * @param string|null $documentLabel the title of that document when the comment was written
     * @param NodeTypeName $nodeType the node's type when the comment was written
     * @param string $nodeLabel the node's label when the comment was written
     * @param WorkspaceName $workspaceName the workspace the comment was written in: read and change the node there
     * @param DimensionSpacePoint $dimensionSpacePoint the dimension space point the comment was written in
     * @param string|null $propertyName the property the comment is about, null for the node as a whole
     * @param string $createdAt when it was written, as ISO 8601
     * @param string|null $resolvedAt when it was resolved, as ISO 8601, null while it is open
     */
    public function __construct(
        public CommentId $id,
        public NodeAggregateId $nodeAggregateId,
        public NodeAggregateId|null $documentNodeAggregateId,
        public string|null $documentLabel,
        public NodeTypeName $nodeType,
        public string $nodeLabel,
        public WorkspaceName $workspaceName,
        public DimensionSpacePoint $dimensionSpacePoint,
        public string|null $propertyName,
        public CommentStatus $status,
        public string $text,
        public string $createdAt,
        public string|null $resolvedAt,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
