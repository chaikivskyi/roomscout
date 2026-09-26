<?php

namespace App\Project\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ProjectCreationLimits extends Constraint
{
    public string $quotaMessage = 'You have used all {{ limit }} of your free searches.';

    public string $imageSizeMessage = 'The image must not be larger than {{ limit }} MB.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
