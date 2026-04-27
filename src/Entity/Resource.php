<?php

namespace App\Entity;

use App\Repository\ResourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResourceRepository::class)]
class Resource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    // pending | published | rejected
    #[ORM\Column(length: 50)]
    private string $status = 'pending';

    // public | private (accès restreint aux connectés)
    #[ORM\Column(length: 50)]
    private string $visibility = 'public';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'resources')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\ManyToOne(inversedBy: 'resources')]
    private ?Category $category = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'resource', orphanRemoval: true)]
    private Collection $comments;

    /**
     * @var Collection<int, ResourceInteraction>
     */
    #[ORM\OneToMany(targetEntity: ResourceInteraction::class, mappedBy: 'resource', orphanRemoval: true)]
    private Collection $resourceInteractions;

    /**
     * @var Collection<int, Statistics>
     */
    #[ORM\OneToMany(targetEntity: Statistics::class, mappedBy: 'resource', orphanRemoval: true)]
    private Collection $statistics;

    public function __construct()
    {
        $this->createdAt           = new \DateTimeImmutable();
        $this->comments            = new ArrayCollection();
        $this->resourceInteractions = new ArrayCollection();
        $this->statistics          = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getVisibility(): string { return $this->visibility; }
    public function setVisibility(string $visibility): static { $this->visibility = $visibility; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setResource($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getResource() === $this) {
                $comment->setResource(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, ResourceInteraction> */
    public function getResourceInteractions(): Collection { return $this->resourceInteractions; }

    public function addResourceInteraction(ResourceInteraction $ri): static
    {
        if (!$this->resourceInteractions->contains($ri)) {
            $this->resourceInteractions->add($ri);
            $ri->setResource($this);
        }
        return $this;
    }

    public function removeResourceInteraction(ResourceInteraction $ri): static
    {
        if ($this->resourceInteractions->removeElement($ri)) {
            if ($ri->getResource() === $this) {
                $ri->setResource(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Statistics> */
    public function getStatistics(): Collection { return $this->statistics; }

    public function addStatistic(Statistics $statistic): static
    {
        if (!$this->statistics->contains($statistic)) {
            $this->statistics->add($statistic);
            $statistic->setResource($this);
        }
        return $this;
    }

    public function removeStatistic(Statistics $statistic): static
    {
        if ($this->statistics->removeElement($statistic)) {
            if ($statistic->getResource() === $this) {
                $statistic->setResource(null);
            }
        }
        return $this;
    }
}
