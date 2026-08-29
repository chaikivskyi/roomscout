<?php

namespace App\Tests\Application\Project;

use App\Api\Bus\QueryBusInterface;
use App\Project\Exception\ProjectNotFound;
use App\Project\Query\GetProject;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class GetProjectQueryTest extends ApiTestCase
{
    public function testReturnsTheProjectsOwnFieldsOnly(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        ProjectContextFactory::new(['project' => $project, 'prompt' => 'a walnut coffee table'])->completed()->create();
        ProjectContextFactory::createOne(['project' => $project, 'prompt' => 'a floor lamp']);

        $output = $this->queryBus()->ask(new GetProject($project->getId(), $user->getId()));

        self::assertSame($project->getId()->toRfc4122(), $output->id);
        self::assertEquals($project->getCreatedAt(), $output->createdAt);
    }

    public function testProjectWhoseContextsWereAllDeletedStillReads(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $output = $this->queryBus()->ask(new GetProject($project->getId(), $user->getId()));

        self::assertSame($project->getId()->toRfc4122(), $output->id);
    }

    public function testUnknownProjectStillThrowsProjectNotFound(): void
    {
        $user = UserFactory::createOne();

        $this->expectException(ProjectNotFound::class);

        $this->queryBus()->ask(new GetProject(Uuid::v7(), $user->getId()));
    }

    private function queryBus(): QueryBusInterface
    {
        return static::getContainer()->get(QueryBusInterface::class);
    }
}
