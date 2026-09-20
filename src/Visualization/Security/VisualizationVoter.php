<?php

namespace App\Visualization\Security;

use App\Api\Security\ActorProviderInterface;
use App\Visualization\Repository\ProductVisualizationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * @extends Voter<string, mixed>
 */
final class VisualizationVoter extends Voter
{
    public const string OWNER = 'VISUALIZATION_OWNER';

    public function __construct(
        private readonly ProductVisualizationRepository $visualizations,
        private readonly ActorProviderInterface $actor,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWNER === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        try {
            $actorId = $this->actor->requireCurrentId();
        } catch (AccessDeniedException) {
            return false;
        }

        if ($subject instanceof Uuid) {
            $visualizationId = $subject;
        } elseif (\is_string($subject)) {
            if (!Uuid::isValid($subject)) {
                return true;
            }

            $visualizationId = Uuid::fromString($subject);
        } else {
            return false;
        }

        $visualization = $this->visualizations->find($visualizationId);

        return null === $visualization || $visualization->getProject()->getUser()->getId()->equals($actorId);
    }
}
