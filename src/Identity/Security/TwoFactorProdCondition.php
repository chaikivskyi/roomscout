<?php

namespace App\Identity\Security;

use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * 2FA is a production requirement only: dev and test log in with password alone.
 */
final class TwoFactorProdCondition implements TwoFactorConditionInterface
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        return 'prod' === $this->environment;
    }
}
