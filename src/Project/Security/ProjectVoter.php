<?php

namespace App\Project\Security;

use App\Identity\Entity\User;
use App\Project\Repository\ProjectRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Uid\Uuid;

/**
 * @extends Voter<string, mixed>
 */
final class ProjectVoter extends Voter
{
    public const string OWNER = 'PROJECT_OWNER';

    public function __construct(
        private readonly ProjectRepository $projects,
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
            $projectId = $subject;
        } elseif (\is_string($subject)) {
            if (!Uuid::isValid($subject)) {
                return true;
            }

            $projectId = Uuid::fromString($subject);
        } else {
            return false;
        }

        $project = $this->projects->find($projectId);

        return null === $project || $project->getUser()->getId()->equals($user->getId());
    }
}
