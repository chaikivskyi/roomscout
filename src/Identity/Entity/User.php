<?php

namespace App\Identity\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Identity\ApiResource\SignupInput;
use App\Identity\ApiResource\SignupOutput;
use App\Identity\Repository\UserRepository;
use App\Identity\State\SignupProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/users/{id}',
            openapi: new Operation(
                tags: ['Identity / Users'],
                summary: 'Get a user profile',
                description: 'Users can only read their own profile.',
            ),
            security: "is_granted('ROLE_USER') and object == user",
        ),
        new Post(
            uriTemplate: '/signup',
            openapi: new Operation(
                tags: ['Identity / Account'],
                summary: 'Create an account (and log in)',
                description: 'Creates the account and returns a JWT, so no separate login call is needed.',
            ),
            input: SignupInput::class,
            output: SignupOutput::class,
            normalizationContext: ['groups' => ['signup:read']],
            processor: SignupProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['user:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
}
