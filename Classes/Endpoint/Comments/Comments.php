<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Endpoint\Comments;

use Beromir\BackendComments\Domain\Model;
use Beromir\BackendComments\Domain\Repository\CommentRepository;
use Beromir\BackendComments\Endpoint\Comments\RequestBody\CommentCreate;
use Beromir\BackendComments\Endpoint\Comments\Response\CommentCreated;
use Beromir\BackendComments\Endpoint\Comments\Schema\Comment;
use Beromir\BackendComments\Endpoint\Comments\Schema\CommentId;
use Beromir\BackendComments\Endpoint\Comments\Schema\CommentList;
use Beromir\BackendComments\Endpoint\Comments\Schema\CommentListing;
use Beromir\BackendComments\Endpoint\Comments\Schema\CommentStatus;
use Beromir\BackendComments\Security\CommentScopes;
use Neos\Api\Endpoint\ContentRepositories\Schema\ContentRepositoryId;
use Neos\Api\Endpoint\Nodes\Domain\SubgraphResolver;
use Neos\Api\Endpoint\Nodes\Parameter\NodeAggregateIds;
use Neos\Api\Endpoint\Nodes\Schema\DimensionSpacePoint;
use Neos\Api\Endpoint\Nodes\Schema\NodeAggregateId;
use Neos\Api\Endpoint\NodeTypes\Schema\NodeTypeName;
use Neos\Api\Endpoint\Workspaces\Domain\WorkspaceResolver;
use Neos\Api\Endpoint\Workspaces\Schema\WorkspaceName;
use Neos\Api\Infrastructure\ContentRepository\ContentRepositoryFinder;
use Neos\Api\Infrastructure\ContentRepository\NodeSerializer;
use Neos\Api\Security\ApiAuthContextProvider;
use Neos\Api\Security\ApiCaller;
use Neos\Api\Shared\Response\BadRequest;
use Neos\Api\Shared\Response\Forbidden;
use Neos\Api\Shared\Response\NotFound;
use Neos\Api\Shared\Response\UnprocessableContent;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Flow\Utility\Algorithms;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\Parameter;
use Neos\OpenApi\Attributes\RequestBody;

/**
 * Comments on nodes, as notes for oneself and one's agent: an editor points
 * at a node and says what should change, an agent acting as the same user
 * (through the MCP server) reads the open comments, makes the changes and
 * resolves them
 *
 * A comment is private to its author: every operation only sees the caller's
 * own comments, the others are a 404, as unknown ones.
 */
final readonly class Comments
{
    public function __construct(
        private CommentRepository $commentRepository,
        private ContentRepositoryFinder $contentRepositoryFinder,
        private SubgraphResolver $subgraphResolver,
        private NodeSerializer $nodeSerializer,
        private WorkspaceResolver $workspaceResolver,
    ) {
    }

    #[Operation(
        path: '/cr/{contentRepositoryId}/comments',
        method: 'GET',
        summary: 'List your comments',
        description: 'The caller\'s comments in the content repository, newest first. The filters narrow them down and combine: filterByNodeAggregateId the comments on these nodes, filterByDocumentNodeAggregateId the comments on a document and the content on it, filterByStatus OPEN the ones still to be dealt with. A comment names the node, its label and type, its document and the document\'s title when it was written, the property it is about (if any), and the workspace and dimension space point it was written in: read the node there (getNode) to see what the author saw, and change it in that workspace.',
        operationId: 'listComments',
        security: [
            ApiAuthContextProvider::SCOPES => [CommentScopes::READ],
        ],
    )]
    public function list(
        #[AuthContext] ApiCaller $caller,
        ContentRepositoryId $contentRepositoryId,
        #[Parameter(in: 'query', description: 'Only the comments on these nodes')]
        NodeAggregateIds|null $filterByNodeAggregateId = null,
        #[Parameter(in: 'query', description: 'Only the comments on this document (page) and the content on it')]
        NodeAggregateId|null $filterByDocumentNodeAggregateId = null,
        #[Parameter(in: 'query', description: 'Only the comments with this status, all if omitted')]
        CommentStatus|null $filterByStatus = null,
    ): CommentListing|NotFound|Forbidden {
        $user = $this->requireUserOf($caller);
        if (!$user instanceof User) {
            return $user;
        }
        $contentRepository = $this->contentRepository($contentRepositoryId);
        if (!$contentRepository instanceof ContentRepository) {
            return $contentRepository;
        }
        $comments = $this->commentRepository->findByAuthor(
            $contentRepository->id,
            $user->getId(),
            $filterByNodeAggregateId?->toNodeAggregateIds(),
            $filterByDocumentNodeAggregateId?->toNodeAggregateId(),
            $filterByStatus !== null ? Model\CommentStatus::from($filterByStatus->value) : null,
        );
        return CommentListing::of(new CommentList(...array_map($this->commentSchema(...), $comments)));
    }

    #[Operation(
        path: '/cr/{contentRepositoryId}/comments/{commentId}',
        method: 'GET',
        summary: 'Get one of your comments',
        description: 'The comment, as listComments has it.',
        operationId: 'getComment',
        security: [
            ApiAuthContextProvider::SCOPES => [CommentScopes::READ],
        ],
    )]
    public function get(#[AuthContext] ApiCaller $caller, ContentRepositoryId $contentRepositoryId, CommentId $commentId): Comment|NotFound|Forbidden
    {
        $comment = $this->resolve($caller, $contentRepositoryId, $commentId);
        return $comment instanceof Model\Comment ? $this->commentSchema($comment) : $comment;
    }

    #[Operation(
        path: '/cr/{contentRepositoryId}/comments',
        method: 'POST',
        summary: 'Comment on a node',
        description: 'Adds an OPEN comment of the caller on the node as it is in the workspace and dimension space point, optionally about one of its properties. The node\'s label and type and its document\'s id and title are kept with the comment. An empty text or a property the node type doesn\'t have is a 422, a node the account may not read a 404.',
        operationId: 'createComment',
        security: [
            ApiAuthContextProvider::SCOPES => [CommentScopes::UPDATE],
        ],
    )]
    public function create(
        #[AuthContext] ApiCaller $caller,
        ContentRepositoryId $contentRepositoryId,
        #[RequestBody(description: 'The comment and the node it is about')] CommentCreate $newComment,
    ): CommentCreated|NotFound|BadRequest|Forbidden|UnprocessableContent {
        $user = $this->requireUserOf($caller);
        if (!$user instanceof User) {
            return $user;
        }
        $text = trim($newComment->text);
        if ($text === '') {
            return UnprocessableContent::because('The comment must not be empty');
        }
        $subgraph = $this->subgraphResolver->resolve($contentRepositoryId, $newComment->workspaceName, $newComment->dimensionSpacePoint, false);
        if ($subgraph instanceof NotFound || $subgraph instanceof BadRequest) {
            return $subgraph;
        }
        $node = $this->subgraphResolver->resolveNodeIn($subgraph, $newComment->nodeAggregateId->toNodeAggregateId());
        if (!$node instanceof Node) {
            return $node;
        }
        $contentRepository = $this->contentRepositoryFinder->find($node->contentRepositoryId);
        $propertyName = $newComment->propertyName !== null ? trim($newComment->propertyName) : null;
        if ($propertyName === '') {
            $propertyName = null;
        }
        if ($propertyName !== null && $contentRepository?->getNodeTypeManager()->getNodeType($node->nodeTypeName)?->hasProperty($propertyName) !== true) {
            return UnprocessableContent::because(sprintf('The node type %s has no property %s', $node->nodeTypeName->value, $propertyName));
        }
        $document = $subgraph->findClosestNode($node->aggregateId, FindClosestNodeFilter::create(nodeTypes: NodeTypeNameFactory::NAME_DOCUMENT));
        $comment = new Model\Comment(
            Algorithms::generateUUID(),
            $node->contentRepositoryId,
            $node->aggregateId,
            $document?->aggregateId,
            $document !== null ? $this->nodeSerializer->label($document) : null,
            $node->nodeTypeName,
            $this->nodeSerializer->label($node),
            $node->workspaceName,
            $node->dimensionSpacePoint,
            $propertyName,
            $user->getId(),
            $text,
            Model\CommentStatus::OPEN,
            new \DateTimeImmutable(),
            null,
        );
        $this->commentRepository->add($comment);
        return new CommentCreated($this->commentSchema($comment));
    }

    #[Operation(
        path: '/cr/{contentRepositoryId}/comments/{commentId}/resolve',
        method: 'POST',
        summary: 'Resolve a comment',
        description: 'Marks the comment RESOLVED, once what it asks for is done. Resolving a resolved comment changes nothing.',
        operationId: 'resolveComment',
        security: [
            ApiAuthContextProvider::SCOPES => [CommentScopes::UPDATE],
        ],
    )]
    public function resolveComment(#[AuthContext] ApiCaller $caller, ContentRepositoryId $contentRepositoryId, CommentId $commentId): Comment|NotFound|Forbidden
    {
        return $this->changeStatus($caller, $contentRepositoryId, $commentId, Model\CommentStatus::RESOLVED);
    }

    #[Operation(
        path: '/cr/{contentRepositoryId}/comments/{commentId}/reopen',
        method: 'POST',
        summary: 'Reopen a comment',
        description: 'Marks a resolved comment OPEN again.',
        operationId: 'reopenComment',
        security: [
            ApiAuthContextProvider::SCOPES => [CommentScopes::UPDATE],
        ],
    )]
    public function reopenComment(#[AuthContext] ApiCaller $caller, ContentRepositoryId $contentRepositoryId, CommentId $commentId): Comment|NotFound|Forbidden
    {
        return $this->changeStatus($caller, $contentRepositoryId, $commentId, Model\CommentStatus::OPEN);
    }

    #[Operation(
        path: '/cr/{contentRepositoryId}/comments/{commentId}',
        method: 'DELETE',
        summary: 'Delete a comment',
        description: 'Deletes the comment.',
        operationId: 'deleteComment',
        security: [
            ApiAuthContextProvider::SCOPES => [CommentScopes::UPDATE],
        ],
    )]
    public function delete(#[AuthContext] ApiCaller $caller, ContentRepositoryId $contentRepositoryId, CommentId $commentId): NotFound|Forbidden|null
    {
        $comment = $this->resolve($caller, $contentRepositoryId, $commentId);
        if (!$comment instanceof Model\Comment) {
            return $comment;
        }
        $this->commentRepository->remove($comment->id);
        return null;
    }

    private function changeStatus(ApiCaller $caller, ContentRepositoryId $contentRepositoryId, CommentId $commentId, Model\CommentStatus $status): Comment|NotFound|Forbidden
    {
        $comment = $this->resolve($caller, $contentRepositoryId, $commentId);
        if (!$comment instanceof Model\Comment) {
            return $comment;
        }
        if ($comment->status !== $status) {
            $this->commentRepository->updateStatus($comment->id, $status, new \DateTimeImmutable());
        }
        $updated = $this->commentRepository->find($comment->contentRepositoryId, $comment->id);
        return $updated !== null ? $this->commentSchema($updated) : NotFound::because(sprintf('There is no comment %s', $commentId->value));
    }

    private function contentRepository(ContentRepositoryId $contentRepositoryId): ContentRepository|NotFound
    {
        return $this->contentRepositoryFinder->find($contentRepositoryId->toContentRepositoryId())
            ?? NotFound::contentRepository($contentRepositoryId->value);
    }

    private function resolve(ApiCaller $caller, ContentRepositoryId $contentRepositoryId, CommentId $commentId): Model\Comment|NotFound|Forbidden
    {
        $user = $this->requireUserOf($caller);
        if (!$user instanceof User) {
            return $user;
        }
        $contentRepository = $this->contentRepository($contentRepositoryId);
        if (!$contentRepository instanceof ContentRepository) {
            return $contentRepository;
        }
        $comment = $this->commentRepository->find($contentRepository->id, $commentId->value);
        return $comment !== null && $comment->authorUserId->equals($user->getId())
            ? $comment
            : NotFound::because(sprintf('You have no comment %s in the content repository %s', $commentId->value, $contentRepositoryId->value));
    }

    private function requireUserOf(ApiCaller $caller): User|Forbidden
    {
        return $this->workspaceResolver->userOf($caller->account)
            ?? Forbidden::because('This operation requires an access token of a Neos user');
    }

    private function commentSchema(Model\Comment $comment): Comment
    {
        return new Comment(
            CommentId::fromString($comment->id),
            NodeAggregateId::fromString($comment->nodeAggregateId->value),
            $comment->documentNodeAggregateId !== null ? NodeAggregateId::fromString($comment->documentNodeAggregateId->value) : null,
            $comment->documentLabel,
            NodeTypeName::fromString($comment->nodeTypeName->value),
            $comment->nodeLabel,
            WorkspaceName::fromString($comment->workspaceName->value),
            DimensionSpacePoint::from($comment->dimensionSpacePoint),
            $comment->propertyName,
            CommentStatus::from($comment->status->value),
            $comment->text,
            $comment->createdAt->format(\DateTimeInterface::ATOM),
            $comment->resolvedAt?->format(\DateTimeInterface::ATOM),
        );
    }
}
