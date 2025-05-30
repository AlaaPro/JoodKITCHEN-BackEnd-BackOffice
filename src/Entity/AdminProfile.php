<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\AdminProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdminProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['admin_profile:read']],
            denormalizationContext: ['groups' => ['admin_profile:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['admin_profile:read']],
            denormalizationContext: ['groups' => ['admin_profile:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['admin_profile:read']],
            denormalizationContext: ['groups' => ['admin_profile:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['admin_profile:read']],
    denormalizationContext: ['groups' => ['admin_profile:write']]
)]
class AdminProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['admin_profile:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'adminProfile', targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['admin_profile:read', 'admin_profile:write'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['admin_profile:read', 'admin_profile:write', 'user:read'])]
    private array $rolesInternes = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['admin_profile:read', 'admin_profile:write', 'user:read'])]
    private ?array $permissionsAvancees = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['admin_profile:read', 'admin_profile:write', 'user:read'])]
    private ?string $notesInterne = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['admin_profile:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['admin_profile:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->rolesInternes = [];
    }

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

    public function getRolesInternes(): array
    {
        return $this->rolesInternes;
    }

    public function setRolesInternes(array $rolesInternes): static
    {
        $this->rolesInternes = $rolesInternes;
        return $this;
    }

    public function getPermissionsAvancees(): ?array
    {
        return $this->permissionsAvancees;
    }

    public function setPermissionsAvancees(?array $permissionsAvancees): static
    {
        $this->permissionsAvancees = $permissionsAvancees;
        return $this;
    }

    public function getNotesInterne(): ?string
    {
        return $this->notesInterne;
    }

    public function setNotesInterne(?string $notesInterne): static
    {
        $this->notesInterne = $notesInterne;
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

    /**
     * Add a role to the internal roles
     */
    public function addRoleInterne(string $role): static
    {
        if (!in_array($role, $this->rolesInternes)) {
            $this->rolesInternes[] = $role;
        }
        return $this;
    }

    /**
     * Remove a role from the internal roles
     */
    public function removeRoleInterne(string $role): static
    {
        $this->rolesInternes = array_values(array_filter(
            $this->rolesInternes,
            fn($r) => $r !== $role
        ));
        return $this;
    }

    /**
     * Check if user has a specific internal role
     */
    public function hasRoleInterne(string $role): bool
    {
        return in_array($role, $this->rolesInternes);
    }
} 