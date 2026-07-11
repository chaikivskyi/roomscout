<?php

namespace App\Identity\ApiResource;

use App\Identity\Validator\UniqueUserEmail;
use Symfony\Component\Validator\Constraints as Assert;

final class SignupInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[UniqueUserEmail]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    public string $password = '';
}
