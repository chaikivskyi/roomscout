<?php

namespace App\Project\Security;

use App\Identity\Entity\User;
use App\Project\Repository\ProjectContextRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Uid\Uuid;

/**
 * @extends Voter<string, mixed>
 */
final class ProjectContextVoter extends Voter
{
    public const string OWNER = 'CONTEXT_OWNER';

    public function __construct(
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWNER === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($subject instanceof Uuid) {
            $contextId = $subject;
        } elseif (\is_string($subject)) {
            if (!Uuid::isValid($subject)) {
                return true;
            }

            $contextId = Uuid::fromString($subject);
        } else {
            return false;
        }

        $context = $this->contexts->find($contextId);

        return null === $context || $context->getProject()->getUser()->getId()->equals($user->getId());
    }
}
