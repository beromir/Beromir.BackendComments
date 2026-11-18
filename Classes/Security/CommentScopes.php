<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Security;

/**
 * The matchers of the ApiPrivilege targets in Policy.yaml
 */
final class CommentScopes
{
    public const string READ = 'comments.read';
    public const string UPDATE = 'comments.update';
}
