<?php

namespace App\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @implements ProviderInterface<object>
 */
#[AsDecorator('api_platform.state_provider.main', priority: -100)]
final class RateLimitProvider implements ProviderInterface
{
    public const string LIMITER = 'rate_limiter';

    /**
     * @param ProviderInterface<object>                             $decorated
     * @param ServiceProviderInterface<RateLimiterFactoryInterface> $limiters
     */
    public function __construct(
        private readonly ProviderInterface $decorated,
        #[AutowireLocator('rate_limiter', indexAttribute: 'name')]
        private readonly ServiceProviderInterface $limiters,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $limiter = $operation->getExtraProperties()[self::LIMITER] ?? null;
        $request = $context['request'] ?? null;

        if (\is_string($limiter) && $request instanceof Request) {
            $limit = $this->limiters->get($limiter)->create($request->getClientIp() ?? 'unknown')->consume();

            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many requests from this address; try again later.');
            }
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
