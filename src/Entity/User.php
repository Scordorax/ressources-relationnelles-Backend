<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * Null si compte FranceConnect sans mot de passe défini
     */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    private ?string $lastname = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Identifiant unique renvoyé par FranceConnect (sub du token OIDC)
     */
    #[ORM\Column(nullable: true, unique: true)]
    private ?string $franceConnectId = null;

    /**
     * Source de création du compte : 'local' | 'france_connect'
     */
    #[ORM\Column(length: 50)]
    private string $authProvider = 'local';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isVerified = false;
    }

    // --- Getters / Setters ---

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): self { $this->password = $password; return $this; }

    public function getFirstname(): ?string { return $this->firstname; }
    public function setFirstname(string $firstname): self { $this->firstname = $firstname; return $this; }

    public function getLastname(): ?string { return $this->lastname; }
    public function setLastname(string $lastname): self { $this->lastname = $lastname; return $this; }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $v): self { $this->isVerified = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getFranceConnectId(): ?string { return $this->franceConnectId; }
    public function setFranceConnectId(?string $id): self { $this->franceConnectId = $id; return $this; }

    public function getAuthProvider(): string { return $this->authProvider; }
    public function setAuthProvider(string $p): self { $this->authProvider = $p; return $this; }

    public function eraseCredentials(): void {}

    public function isFranceConnectAccount(): bool
    {
        return $this->authProvider === 'france_connect';
    }
}
