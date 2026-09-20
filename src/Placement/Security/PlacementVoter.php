<?php

namespace App\Placement\Security;

use App\Api\Security\ActorProviderInterface;
use App\Placement\Repository\ProductPlacementRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * @extends Voter<string, mixed>
 */
final class PlacementVoter extends Voter
{
    public const string OWNER = 'PLACEMENT_OWNER';

    public function __construct(
        private readonly ProductPlacementRepository $placements,
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
            $placementId = $subject;
        } elseif (\is_string($subject)) {
            if (!Uuid::isValid($subject)) {
                return true;
            }

            $placementId = Uuid::fromString($subject);
        } else {
            return false;
        }

        $placement = $this->placements->find($placementId);

        return null === $placement || $placement->getProject()->getUser()->getId()->equals($actorId);
    }
}
