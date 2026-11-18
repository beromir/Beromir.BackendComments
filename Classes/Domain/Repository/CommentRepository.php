<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Domain\Repository;

use Beromir\BackendComments\Domain\Model\Comment;
use Beromir\BackendComments\Domain\Model\CommentStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Model\UserId;

/**
 * Plain DBAL storage, no security: that is up to the endpoint
 *
 * @internal
 */
#[Flow\Scope('singleton')]
final readonly class CommentRepository
{
    private const string TABLE = 'beromir_backendcomments_comment';

    public function __construct(
        private Connection $dbal,
    ) {
    }

    public function add(Comment $comment): void
    {
        $this->dbal->insert(self::TABLE, [
            'id' => $comment->id,
            'content_repository_id' => $comment->contentRepositoryId->value,
            'node_aggregate_id' => $comment->nodeAggregateId->value,
            'document_node_aggregate_id' => $comment->documentNodeAggregateId?->value,
            'document_label' => $comment->documentLabel !== null ? mb_substr($comment->documentLabel, 0, 255) : null,
            'node_type' => $comment->nodeTypeName->value,
            'node_label' => mb_substr($comment->nodeLabel, 0, 255),
            'workspace_name' => $comment->workspaceName->value,
            'dimension_space_point' => $comment->dimensionSpacePoint->toJson(),
            'property_name' => $comment->propertyName,
            'author_user_id' => $comment->authorUserId->value,
            'text' => $comment->text,
            'status' => $comment->status->value,
            'created_at' => self::toDatabase($comment->createdAt),
            'resolved_at' => null,
        ]);
    }

    public function find(ContentRepositoryId $contentRepositoryId, string $id): ?Comment
    {
        $row = $this->dbal->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE content_repository_id = :contentRepositoryId AND id = :id',
            ['contentRepositoryId' => $contentRepositoryId->value, 'id' => $id],
        );
        return $row !== false ? $this->mapComment($row) : null;
    }

    /**
     * The author's comments matching all given filters, newest first
     *
     * @param list<NodeAggregateId>|null $nodeAggregateIds
     * @return list<Comment>
     */
    public function findByAuthor(
        ContentRepositoryId $contentRepositoryId,
        UserId $authorUserId,
        ?array $nodeAggregateIds = null,
        ?NodeAggregateId $documentNodeAggregateId = null,
        ?CommentStatus $status = null,
    ): array {
        $query = $this->dbal->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->where('content_repository_id = :contentRepositoryId')
            ->andWhere('author_user_id = :authorUserId')
            ->setParameter('contentRepositoryId', $contentRepositoryId->value)
            ->setParameter('authorUserId', $authorUserId->value)
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC');
        if ($nodeAggregateIds !== null) {
            $query->andWhere('node_aggregate_id IN (:nodeAggregateIds)')
                ->setParameter('nodeAggregateIds', array_map(static fn (NodeAggregateId $id) => $id->value, $nodeAggregateIds), ArrayParameterType::STRING);
        }
        if ($documentNodeAggregateId !== null) {
            $query->andWhere('document_node_aggregate_id = :documentNodeAggregateId')
                ->setParameter('documentNodeAggregateId', $documentNodeAggregateId->value);
        }
        if ($status !== null) {
            $query->andWhere('status = :status')->setParameter('status', $status->value);
        }
        return array_map($this->mapComment(...), $query->executeQuery()->fetchAllAssociative());
    }

    public function updateStatus(string $id, CommentStatus $status, \DateTimeImmutable $at): void
    {
        $this->dbal->update(self::TABLE, [
            'status' => $status->value,
            'resolved_at' => $status === CommentStatus::RESOLVED ? self::toDatabase($at) : null,
        ], ['id' => $id]);
    }

    public function remove(string $id): void
    {
        $this->dbal->delete(self::TABLE, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapComment(array $row): Comment
    {
        return new Comment(
            $row['id'],
            ContentRepositoryId::fromString($row['content_repository_id']),
            NodeAggregateId::fromString($row['node_aggregate_id']),
            $row['document_node_aggregate_id'] !== null ? NodeAggregateId::fromString($row['document_node_aggregate_id']) : null,
            $row['document_label'],
            NodeTypeName::fromString($row['node_type']),
            $row['node_label'],
            WorkspaceName::fromString($row['workspace_name']),
            DimensionSpacePoint::fromJsonString($row['dimension_space_point']),
            $row['property_name'],
            UserId::fromString($row['author_user_id']),
            $row['text'],
            CommentStatus::from($row['status']),
            self::fromDatabase($row['created_at']),
            $row['resolved_at'] !== null ? self::fromDatabase($row['resolved_at']) : null,
        );
    }

    private static function toDatabase(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private static function fromDatabase(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}
