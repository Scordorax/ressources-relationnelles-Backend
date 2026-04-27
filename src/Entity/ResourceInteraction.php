<?php

namespace App\Entity;

use App\Repository\ResourceInteractionRepository;
use Doctrine\ORM\Mapping as ORM;

// Types : favorite | progress | aside | share
#[ORM\Entity(repositoryClass: ResourceInteractionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_RESOURCE_TYPE', fields: ['user', 'resource', 'type'])]
class ResourceInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'resourceInteractions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'resourceInteractions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Resource $resource = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getResource(): ?Resource { return $this->resource; }
    public function setResource(?Resource $resource): static { $this->resource = $resource; return $this; }
}
