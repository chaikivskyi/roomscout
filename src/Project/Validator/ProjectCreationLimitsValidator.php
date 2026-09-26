<?php

namespace App\Project\Validator;

use App\Identity\Entity\User;
use App\Project\ApiResource\ProjectRequest;
use App\Project\Repository\ProjectRepository;
use Craue\ConfigBundle\Util\Config;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ProjectCreationLimitsValidator extends ConstraintValidator
{
    private const int BYTES_PER_MB = 1_000_000;

    public function __construct(
        private readonly Security $security,
        private readonly Config $config,
        private readonly ProjectRepository $projects,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ProjectCreationLimits) {
            throw new UnexpectedTypeException($constraint, ProjectCreationLimits::class);
        }

        if (!$value instanceof ProjectRequest) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $maxMb = $this->positiveIntOrNull($this->setting('free_max_image_size_mb'));

        if (null !== $value->image && null !== $maxMb && $value->image->getSize() > $maxMb * self::BYTES_PER_MB) {
            $this->context->buildViolation($constraint->imageSizeMessage)
                ->setParameter('{{ limit }}', (string) $maxMb)
                ->atPath('image')
                ->addViolation();
        }

        $freeSearches = $this->intOrNull($this->setting('free_search_count'));

        if (null !== $freeSearches && $this->projects->countForUser($user->getId()) >= $freeSearches) {
            $this->context->buildViolation($constraint->quotaMessage)
                ->setParameter('{{ limit }}', (string) $freeSearches)
                ->addViolation();
        }
    }

    private function setting(string $name): ?string
    {
        try {
            return $this->config->get($name);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function intOrNull(?string $value): ?int
    {
        return null !== $value ? (int) $value : null;
    }

    private function positiveIntOrNull(?string $value): ?int
    {
        $int = $this->intOrNull($value);

        return null !== $int && $int > 0 ? $int : null;
    }
}
