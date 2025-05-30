<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\KitchenProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KitchenProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['kitchen_profile:read']],
            denormalizationContext: ['groups' => ['kitchen_profile:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['kitchen_profile:read']],
            denormalizationContext: ['groups' => ['kitchen_profile:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['kitchen_profile:read']],
            denormalizationContext: ['groups' => ['kitchen_profile:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['kitchen_profile:read']],
    denormalizationContext: ['groups' => ['kitchen_profile:write']]
)]
class KitchenProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['kitchen_profile:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'kitchenProfile', targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?string $posteCuisine = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?string $disponibilite = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['kitchen_profile:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['kitchen_profile:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPosteCuisine(): ?string
    {
        return $this->posteCuisine;
    }

    public function setPosteCuisine(string $posteCuisine): static
    {
        $this->posteCuisine = $posteCuisine;
        return $this;
    }

    public function getDisponibilite(): ?string
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?string $disponibilite): static
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
} 