<?php

namespace App\Tests\Application\Project;

use App\Api\Bus\QueryBusInterface;
use App\Project\Query\ListProjects;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Factory\UserFactory;

final class ListProjectsQueryTest extends ApiTestCase
{
    public function testReturnsOwnProjectsNewestFirstWithInitialPromptAndLatestImage(): void
    {
        $user = UserFactory::createOne();

        $older = ProjectFactory::createOne(['user' => $user]);
        ProjectContextFactory::createOne(['project' => $older, 'prompt' => 'a beige sofa']);
        ProjectContextFactory::createOne(['project' => $older, 'prompt' => 'the same sofa but green']);
        ProjectImageVersionFactory::createOne(['project' => $older, 'imagePath' => 'uploaded/image.jpg']);
        ProjectImageVersionFactory::createOne(['project' => $older, 'imagePath' => 'placed/image.png']);

        $newer = ProjectFactory::createOne(['user' => $user]);
        ProjectContextFactory::createOne(['project' => $newer, 'prompt' => 'a walnut table']);

        ProjectFactory::createOne();

        $page = $this->queryBus()->ask(new ListProjects($user->getId(), 1, 15));

        self::assertSame(2, $page->total);
        self::assertSame(1, $page->page);
        self::assertSame(15, $page->limit);
        self::assertSame(
            [$newer->getId()->toRfc4122(), $older->getId()->toRfc4122()],
            array_map(static fn ($item): string => $item->id, $page->items),
        );
        self::assertSame(['a walnut table', 'a beige sofa'], array_map(static fn ($item): ?string => $item->prompt, $page->items));
        self::assertNull($page->items[0]->imageUrl);
        self::assertSame('http://localhost/uploads/project/placed/image.png', $page->items[1]->imageUrl);
        self::assertEquals($older->getCreatedAt(), $page->items[1]->createdAt);
    }

    public function testProjectWithoutContextsHasANullPrompt(): void
    {
        $user = UserFactory::createOne();
        ProjectFactory::createOne(['user' => $user]);

        $page = $this->queryBus()->ask(new ListProjects($user->getId(), 1, 15));

        self::assertSame(1, $page->total);
        self::assertNull($page->items[0]->prompt);
    }

    public function testPagesThroughTheProjects(): void
    {
        $user = UserFactory::createOne();
        [$oldest, $middle, $newest] = ProjectFactory::createMany(3, ['user' => $user]);

        $first = $this->queryBus()->ask(new ListProjects($user->getId(), 1, 2));
        $second = $this->queryBus()->ask(new ListProjects($user->getId(), 2, 2));

        self::assertSame(3, $first->total);
        self::assertSame(3, $second->total);
        self::assertSame(
            [$newest->getId()->toRfc4122(), $middle->getId()->toRfc4122()],
            array_map(static fn ($item): string => $item->id, $first->items),
        );
        self::assertSame(
            [$oldest->getId()->toRfc4122()],
            array_map(static fn ($item): string => $item->id, $second->items),
        );
    }

    public function testUserWithoutProjectsGetsAnEmptyPage(): void
    {
        $page = $this->queryBus()->ask(new ListProjects(UserFactory::createOne()->getId(), 1, 15));

        self::assertSame(0, $page->total);
        self::assertSame([], $page->items);
    }

    private function queryBus(): QueryBusInterface
    {
        return static::getContainer()->get(QueryBusInterface::class);
    }
}
