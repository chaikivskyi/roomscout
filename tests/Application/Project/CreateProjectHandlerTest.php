<?php

namespace App\Tests\Application\Project;

use App\Api\Bus\CommandBusInterface;
use App\Project\Command\CreateProject;
use App\Project\Exception\ProjectOwnerNotFound;
use App\Tests\Application\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateProjectHandlerTest extends ApiTestCase
{
    public function testOwnerWhoseRowIsGoneIsAnAuthenticationFailure(): void
    {
        $this->expectException(ProjectOwnerNotFound::class);

        static::getContainer()->get(CommandBusInterface::class)->dispatch(new CreateProject(
            projectId: Uuid::v7(),
            contextId: Uuid::v7(),
            versionId: Uuid::v7(),
            ownerId: Uuid::v7(),
            imagePath: 'nowhere/image.png',
            prompt: 'a floor lamp',
        ));
    }
}
