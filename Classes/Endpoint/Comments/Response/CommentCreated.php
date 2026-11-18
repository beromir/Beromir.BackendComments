<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Endpoint\Comments\Response;

use Beromir\BackendComments\Endpoint\Comments\Schema\Comment;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

final readonly class CommentCreated implements ApiResponse
{
    public function __construct(
        private Comment $body,
    ) {
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(201);
    }

    public static function description(): string
    {
        return 'The comment was created';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(Comment::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public function body(): Comment
    {
        return $this->body;
    }
}
