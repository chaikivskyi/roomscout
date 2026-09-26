<?php

namespace App\Tests\Application\Visualization;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductVisualizationFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;

final class GuestVisualizationTest extends ApiTestCase
{
    public function testAGuestCannotStartAVisualizationOfItsOwnContext(): void
    {
        $guest = UserFactory::new()->guest()->create();
        $project = ProjectFactory::createOne(['user' => $guest]);
        $context = ProjectContextFactory::createOne(['project' => $project]);
        $product = ProductFactory::createOne();

        $this->authClient($this->tokenFor($guest))->request('POST', '/api/visualizations', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'contextId' => $context->getId()->toRfc4122(),
                'productId' => $product->getId()->toRfc4122(),
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAGuestCannotReadAVisualizationOfItsOwnProject(): void
    {
        $guest = UserFactory::new()->guest()->create();
        $project = ProjectFactory::createOne(['user' => $guest]);
        $visualization = ProductVisualizationFactory::createOne(['project' => $project]);

        $this->authClient($this->tokenFor($guest))
            ->request('GET', '/api/visualizations/'.$visualization->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }
}
