<?php

namespace App\Identity\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Identity\ApiResource\SignupInput;
use App\Identity\ApiResource\SignupOutput;
use App\Identity\Enum\Role;
use App\Identity\Repository\UserRepository;
use App\Identity\State\CurrentUserProvider;
use App\Identity\State\SignupProcessor;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me',
            openapi: new Operation(
                tags: ['Identity / Users'],
                summary: 'Get the current user profile',
                description: 'Returns the profile of the authenticated user. `guest` is true for an anonymous guest session, whose `email` is null; sign up while sending the guest token to convert it into a full account.',
            ),
            security: "is_granted('ROLE_USER') or is_granted('ROLE_GUEST')",
            provider: CurrentUserProvider::class,
        ),
        new Post(
            uriTemplate: '/signup',
            security: "is_granted('PUBLIC_ACCESS')",
            openapi: new Operation(
                tags: ['Identity / Account'],
                summary: 'Create an account (and log in)',
                description: 'Creates the account and returns a JWT, so no separate login call is needed. Send a guest token from POST /api/guest in the Authorization header to convert that guest into this account, keeping its projects and its consumed free searches.',
            ),
            input: SignupInput::class,
            output: SignupOutput::class,
            normalizationContext: ['groups' => ['signup:read']],
            processor: SignupProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['user:read'], 'skip_null_values' => false],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    #[Groups(['user:read'])]
    private Uuid $id;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['user:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastActiveAt;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $totpSecret = null;

    public function __construct(?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->lastActiveAt = $this->createdAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastActiveAt(): \DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function touchLastActiveAt(\DateTimeImmutable $at): void
    {
        $this->lastActiveAt = $at;
    }

    #[Groups(['user:read'])]
    #[SerializedName('guest')]
    public function isGuest(): bool
    {
        return null === $this->email;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        if ($this->isGuest()) {
            return [Role::Guest->value];
        }

        return array_values(array_unique([...$this->roles, Role::User->value]));
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role->value, $this->getRoles(), true);
    }

    public function addRole(Role $role): static
    {
        if (!in_array($role->value, $this->roles, true)) {
            $this->roles[] = $role->value;
        }

        return $this;
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
        return $this->email ?: $this->id->toString();
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
