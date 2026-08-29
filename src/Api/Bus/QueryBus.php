<?php

namespace App\Api\Bus;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class QueryBus implements QueryBusInterface
{
    use HandleTrait;
    use UnwrapsHandlerFailure;

    public function __construct(
        #[Autowire(service: 'query.bus')]
        MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $queryBus;
    }

    /**
     * @template TResult
     *
     * @param QueryInterface<TResult> $query
     *
     * @return TResult
     */
    public function ask(QueryInterface $query): mixed
    {
        try {
            /** @var TResult $result */
            $result = $this->handle($query);
        } catch (HandlerFailedException $e) {
            throw $this->unwrapHandlerFailure($e);
        }

        return $result;
    }
}
