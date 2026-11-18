<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Domain\Model;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Neos\Domain\Model\UserId;

/**
 * A comment on a node, anchored where it was written: the workspace and
 * dimension space point it was seen in, and optionally one of its properties
 */
final readonly class Comment
{
    public function __construct(
        public string $id,
        public ContentRepositoryId $contentRepositoryId,
        public NodeAggregateId $nodeAggregateId,
        public ?NodeAggregateId $documentNodeAggregateId,
        public ?string $documentLabel,
        public NodeTypeName $nodeTypeName,
        public string $nodeLabel,
        public WorkspaceName $workspaceName,
        public DimensionSpacePoint $dimensionSpacePoint,
        public ?string $propertyName,
        public UserId $authorUserId,
        public string $text,
        public CommentStatus $status,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $resolvedAt,
    ) {
    }
}
