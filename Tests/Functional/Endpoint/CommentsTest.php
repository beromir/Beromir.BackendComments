<?php

declare(strict_types=1);

namespace Beromir\BackendComments\Tests\Functional\Endpoint;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Api\Tests\Functional\Endpoint\ContentRepositoryTestCase;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\RootNodeCreation\Command\CreateRootNodeAggregateWithNode;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Persistence\Doctrine\Migrations\Version20261118120000;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\UserService;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;

/**
 * Builds on Neos.Api's test case: it needs the content repository "default" on MariaDB or MySQL (FLOW_CONTEXT=Testing
 * with such a database), on any other database the tests skip themselves
 */
class CommentsTest extends ContentRepositoryTestCase
{
    private const string PATH = '/api/cr/default/comments';
    private const string SITE = 'comments-site';
    private const string PAGE = 'comments-page';
    private const string TEXT_ON_SITE = 'comments-text-on-site';
    private const string TEXT_ON_PAGE = 'comments-text-on-page';

    /**
     * The site's default, which the endpoint takes if a request has none; the nodes are created in it
     */
    private DimensionSpacePoint $dimensionSpacePoint;

    private ?string $authorToken = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireLiveWorkspace();
        $this->createCommentTable();

        $userService = $this->objectManager->get(UserService::class);
        $userService->createUser('author', 'password', 'Ada', 'Author', ['Neos.Neos:Editor']);
        $userService->createUser('other', 'password', 'Otto', 'Other', ['Neos.Neos:Editor']);
        $this->addMachineClient('author-agent', 'author');
        $this->addMachineClient('other-agent', 'other');
        $site = new Site(self::SITE);
        $site->setName('Comments Site');
        $site->setState(Site::STATE_ONLINE);
        $site->setSiteResourcesPackageKey('Beromir.BackendComments');
        $this->objectManager->get(SiteRepository::class)->add($site);
        $this->persistenceManager->persistAll();
        $this->dimensionSpacePoint = $site->getConfiguration()->defaultDimensionSpacePoint;

        $this->handle(CreateRootNodeAggregateWithNode::create(WorkspaceName::forLive(), NodeAggregateId::fromString('sites'), NodeTypeName::fromString('Neos.Neos:Sites')));
        $this->createNode(self::SITE, 'Beromir.BackendComments:Testing.Site', 'sites', ['title' => 'Comments Site'], self::SITE);
        $this->createNode(self::PAGE, 'Beromir.BackendComments:Testing.Page', self::SITE, ['title' => 'Comments Page', 'uriPathSegment' => 'comments-page']);
        $this->createNode(self::TEXT_ON_SITE, 'Beromir.BackendComments:Testing.Text', self::SITE, ['text' => 'On the site']);
        $this->createNode(self::TEXT_ON_PAGE, 'Beromir.BackendComments:Testing.Text', self::PAGE, ['text' => 'On the page']);
    }

    #[Test]
    public function createsACommentWithWhereItWasWritten(): void
    {
        $response = $this->post(self::PATH, $this->authorToken(), ['nodeAggregateId' => self::TEXT_ON_PAGE, 'text' => "  Make it shorter \n", 'workspaceName' => 'live', 'dimensionSpacePoint' => $this->dimensionSpacePoint->toJson(), 'propertyName' => 'text']);

        self::assertSame(201, $response->getStatusCode(), (string)$response->getBody());
        $comment = self::json($response);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $comment['id']);
        self::assertSame([
            'nodeAggregateId' => self::TEXT_ON_PAGE,
            'documentNodeAggregateId' => self::PAGE,
            'documentLabel' => 'Comments Page',
            'nodeType' => 'Beromir.BackendComments:Testing.Text',
            'workspaceName' => 'live',
            'dimensionSpacePoint' => $this->dimensionSpacePoint->toJson(),
            'propertyName' => 'text',
            'status' => 'OPEN',
            'text' => 'Make it shorter',
            'resolvedAt' => null,
        ], array_diff_key($comment, array_flip(['id', 'nodeLabel', 'createdAt'])));
        self::assertNotSame('', $comment['nodeLabel']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $comment['createdAt']));

        // live and the site's dimension space point if omitted, a document is on itself
        $response = $this->post(self::PATH, $this->authorToken(), ['nodeAggregateId' => self::PAGE, 'text' => 'Another title']);
        self::assertSame(201, $response->getStatusCode(), (string)$response->getBody());
        $expected = [
            'nodeAggregateId' => self::PAGE,
            'documentNodeAggregateId' => self::PAGE,
            'documentLabel' => 'Comments Page',
            'nodeLabel' => 'Comments Page',
            'workspaceName' => 'live',
            'dimensionSpacePoint' => $this->dimensionSpacePoint->toJson(),
            'propertyName' => null,
        ];
        self::assertSame($expected, array_intersect_key(self::json($response), $expected));
    }

    #[Test]
    public function rejectsInvalidComments(): void
    {
        $token = $this->authorToken();

        $response = $this->post(self::PATH, $token, ['nodeAggregateId' => self::TEXT_ON_PAGE, 'text' => " \n "]);
        self::assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame('The comment must not be empty', self::json($response)['detail']);

        $response = $this->post(self::PATH, $token, ['nodeAggregateId' => self::TEXT_ON_PAGE, 'text' => 'Bigger', 'propertyName' => 'colour']);
        self::assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame('The node type Beromir.BackendComments:Testing.Text has no property colour', self::json($response)['detail']);

        $response = $this->post(self::PATH, $token, ['nodeAggregateId' => 'no-such-node', 'text' => 'Bigger']);
        self::assertSame(404, $response->getStatusCode(), (string)$response->getBody());

        $response = $this->post('/api/cr/unknown/comments', $token, ['nodeAggregateId' => self::TEXT_ON_PAGE, 'text' => 'Bigger']);
        self::assertSame(404, $response->getStatusCode(), (string)$response->getBody());

        $response = $this->post(self::PATH, $token, ['nodeAggregateId' => self::TEXT_ON_PAGE]);
        self::assertSame(400, $response->getStatusCode(), (string)$response->getBody());

        self::assertSame([], $this->list($token));
    }

    #[Test]
    public function onlyTheAuthorSeesAndChangesAComment(): void
    {
        $id = $this->comment(self::TEXT_ON_PAGE, 'Mine');
        $otherToken = $this->token('other-agent', 'comments.read comments.update');

        self::assertSame([], $this->list($otherToken));
        $responses = [
            'get' => $this->get(self::PATH . '/' . $id, $otherToken),
            'resolve' => $this->post(self::PATH . '/' . $id . '/resolve', $otherToken, []),
            'reopen' => $this->post(self::PATH . '/' . $id . '/reopen', $otherToken, []),
            'delete' => $this->delete(self::PATH . '/' . $id, $otherToken),
        ];
        foreach ($responses as $operation => $response) {
            self::assertSame(404, $response->getStatusCode(), $operation . ': ' . $response->getBody());
            self::assertSame(sprintf('You have no comment %s in the content repository default', $id), self::json($response)['detail']);
        }

        $response = $this->get(self::PATH . '/' . $id, $this->authorToken());
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $comment = self::json($response);
        self::assertSame(['Mine', 'OPEN'], [$comment['text'], $comment['status']]);
        self::assertSame([$id], array_column($this->list($this->authorToken()), 'id'));
    }

    #[Test]
    public function resolvesReopensAndDeletesAComment(): void
    {
        $id = $this->comment(self::TEXT_ON_PAGE, 'Fix the typo');
        $token = $this->authorToken();

        $response = $this->post(self::PATH . '/' . $id . '/resolve', $token, []);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $resolved = self::json($response);
        self::assertSame('RESOLVED', $resolved['status']);
        self::assertIsString($resolved['resolvedAt']);

        // resolving again changes nothing
        $comment = self::json($this->post(self::PATH . '/' . $id . '/resolve', $token, []));
        self::assertSame(['RESOLVED', $resolved['resolvedAt']], [$comment['status'], $comment['resolvedAt']]);

        $response = $this->post(self::PATH . '/' . $id . '/reopen', $token, []);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $comment = self::json($response);
        self::assertSame(['OPEN', null], [$comment['status'], $comment['resolvedAt']]);

        $response = $this->delete(self::PATH . '/' . $id, $token);
        self::assertSame(204, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame(404, $this->get(self::PATH . '/' . $id, $token)->getStatusCode());
        self::assertSame(404, $this->delete(self::PATH . '/' . $id, $token)->getStatusCode());
    }

    #[Test]
    public function filtersByStatusDocumentAndNode(): void
    {
        $token = $this->authorToken();
        $onSite = $this->comment(self::TEXT_ON_SITE, 'On the site');
        $onPage = $this->comment(self::PAGE, 'On the page');
        $onPageContent = $this->comment(self::TEXT_ON_PAGE, 'On the page content');
        $resolved = $this->comment(self::TEXT_ON_PAGE, 'Done already');
        self::assertSame(200, $this->post(self::PATH . '/' . $resolved . '/resolve', $token, [])->getStatusCode());
        $this->comment(self::TEXT_ON_PAGE, 'Not mine', $this->token('other-agent', 'comments.update'));

        self::assertSame([$resolved, $onPageContent, $onPage, $onSite], array_column($this->list($token), 'id'));
        self::assertSame([$onPageContent, $onPage, $onSite], array_column($this->list($token, ['filterByStatus' => 'OPEN']), 'id'));
        self::assertSame([$resolved], array_column($this->list($token, ['filterByStatus' => 'RESOLVED']), 'id'));
        self::assertSame([$resolved, $onPageContent, $onPage], array_column($this->list($token, ['filterByDocumentNodeAggregateId' => self::PAGE]), 'id'));
        self::assertSame([$onPageContent, $onPage], array_column($this->list($token, ['filterByDocumentNodeAggregateId' => self::PAGE, 'filterByStatus' => 'OPEN']), 'id'));
        self::assertSame([$onSite], array_column($this->list($token, ['filterByDocumentNodeAggregateId' => self::SITE]), 'id'));
        self::assertSame([$onPage, $onSite], array_column($this->list($token, ['filterByNodeAggregateId' => self::TEXT_ON_SITE . ',' . self::PAGE]), 'id'));

        $response = $this->get(self::PATH . '?filterByStatus=DONE', $token);
        self::assertSame(400, $response->getStatusCode(), (string)$response->getBody());
    }

    #[Test]
    public function needsTheCommentScopes(): void
    {
        $id = $this->comment(self::TEXT_ON_PAGE, 'Scoped');
        $readOnly = $this->token('author-agent', 'comments.read');

        self::assertSame(200, $this->get(self::PATH, $readOnly)->getStatusCode());
        self::assertSame(403, $this->post(self::PATH, $readOnly, ['nodeAggregateId' => self::TEXT_ON_PAGE, 'text' => 'More'])->getStatusCode());
        self::assertSame(403, $this->post(self::PATH . '/' . $id . '/resolve', $readOnly, [])->getStatusCode());
        self::assertSame(403, $this->delete(self::PATH . '/' . $id, $readOnly)->getStatusCode());
        self::assertSame(401, $this->get(self::PATH, null)->getStatusCode());
    }

    private function authorToken(): string
    {
        return $this->authorToken ??= $this->token('author-agent', 'comments.read comments.update');
    }

    private function comment(string $nodeAggregateId, string $text, ?string $token = null): string
    {
        $response = $this->post(self::PATH, $token ?? $this->authorToken(), ['nodeAggregateId' => $nodeAggregateId, 'text' => $text]);
        self::assertSame(201, $response->getStatusCode(), (string)$response->getBody());
        return self::json($response)['id'];
    }

    /**
     * @param array<string, string> $query
     * @return list<array<string, mixed>>
     */
    private function list(string $token, array $query = []): array
    {
        $response = $this->get(self::PATH . ($query !== [] ? '?' . http_build_query($query) : ''), $token);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $listing = self::json($response);
        self::assertSame(count($listing['data']), $listing['meta']['total']);
        return $listing['data'];
    }

    /**
     * Testable persistence creates the tables of entities, not those of migrations
     */
    private function createCommentTable(): void
    {
        $connection = $this->objectManager->get(EntityManagerInterface::class)->getConnection();
        require_once __DIR__ . '/../../../Migrations/Mysql/Version20261118120000.php';
        $migration = new Version20261118120000($connection, new NullLogger());
        $migration->up(new Schema());
        $connection->executeStatement('DROP TABLE IF EXISTS beromir_backendcomments_comment');
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createNode(string $id, string $nodeTypeName, string $parentId, array $properties, ?string $nodeName = null): void
    {
        $command = CreateNodeAggregateWithNode::create(
            WorkspaceName::forLive(),
            NodeAggregateId::fromString($id),
            NodeTypeName::fromString($nodeTypeName),
            OriginDimensionSpacePoint::fromDimensionSpacePoint($this->dimensionSpacePoint),
            NodeAggregateId::fromString($parentId),
            initialPropertyValues: PropertyValuesToWrite::fromArray($properties),
        );
        $this->handle($nodeName !== null ? $command->withNodeName(NodeName::fromString($nodeName)) : $command);
    }
}
