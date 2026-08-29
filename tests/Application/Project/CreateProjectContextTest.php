<?php

namespace App\Tests\Application\Project;

use App\CatalogSearch\Command\MatchContextProducts;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class CreateProjectContextTest extends ApiTestCase
{
    public function testCreatesContextOnOwnProject(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('POST', '/api/projects/'.$project->getId()->toRfc4122().'/contexts', [
                'json' => ['prompt' => 'the same sofa but in green'],
            ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['prompt' => 'the same sofa but in green', 'status' => 'processing']);

        $data = $response->toArray();
        self::assertIsString($data['id']);
        self::assertNotEmpty($data['id']);
        self::assertNotEmpty($data['createdAt']);

        $context = $this->entityManager()->getRepository(ProjectContext::class)
            ->find(Uuid::fromString($data['id']));
        self::assertNotNull($context);
        self::assertSame('the same sofa but in green', $context->getPrompt());
        self::assertSame(ProjectContextStatus::Processing, $context->getStatus());
        self::assertSame($project->getId()->toRfc4122(), $context->getProject()->getId()->toRfc4122());

        $messages = $this->matchingMessages();
        self::assertCount(1, $messages);
        self::assertSame($data['id'], $messages[0]->contextId);
    }

    public function testUnknownProjectReturns404(): void
    {
        $client = $this->authClient($this->tokenFor(UserFactory::createOne()));

        $client->request('POST', '/api/projects/'.Uuid::v7()->toRfc4122().'/contexts', [
            'json' => ['prompt' => 'a green sofa'],
        ]);
        self::assertResponseStatusCodeSame(404);

        $client->request('POST', '/api/projects/not-a-uuid/contexts', [
            'json' => ['prompt' => 'a green sofa'],
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $project = ProjectFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('POST', '/api/projects/'.$project->getId()->toRfc4122().'/contexts', [
                'json' => ['prompt' => 'a green sofa'],
            ]);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->matchingMessages());
    }

    public function testRequiresAuthentication(): void
    {
        $project = ProjectFactory::createOne();

        static::createClient()->request('POST', '/api/projects/'.$project->getId()->toRfc4122().'/contexts', [
            'json' => ['prompt' => 'a green sofa'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsBlankPrompt(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/contexts';

        $client->request('POST', $url, ['json' => ['prompt' => '']]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'prompt']]]);

        $client->request('POST', $url, ['json' => []]);
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return list<MatchContextProducts>
     */
    private function matchingMessages(): array
    {
        $transport = static::getContainer()->get('messenger.transport.async_embeddings');
        \assert($transport instanceof InMemoryTransport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof MatchContextProducts) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
