<?php

namespace App\Identity\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.openapi.factory', priority: -10)]
final class IdentityOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $paths = $openApi->getPaths();

        $login = $paths->getPath('/api/login');
        if (null !== $login && null !== $login->getPost()) {
            $paths->addPath('/api/login', $login->withPost(
                $login->getPost()
                    ->withTags(['Identity / Account'])
                    ->withSummary('Log in (obtain a JWT)'),
            ));
        }

        $paths->addPath('/api/logout', new PathItem(
            post: new Operation(
                operationId: 'api_logout',
                tags: ['Identity / Account'],
                summary: 'Log out (revoke the current JWT)',
                description: 'Blocklists the JWT sent in the Authorization header; it is rejected on all later requests. Without a token this is a 204 no-op; an invalid or already-revoked token fails authentication with 401.',
                responses: [
                    '204' => new OpenApiResponse(description: 'Token revoked (or no token sent)'),
                    '401' => new OpenApiResponse(description: 'Invalid or already-revoked token'),
                ],
            ),
        ));

        return $openApi;
    }
}
