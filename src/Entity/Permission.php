<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'permissions')]
#[ORM\HasLifecycleCallbacks]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['permission:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    #[Groups(['permission:read', 'permission:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    #[Groups(['permission:read', 'permission:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['permission:read', 'permission:write'])]
    private ?string $category = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['permission:read', 'permission:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['permission:read', 'permission:write'])]
    private int $priority = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['permission:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['permission:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'permissions')]
    private Collection $roles;

    #[ORM\ManyToMany(targetEntity: AdminProfile::class, mappedBy: 'permissions')]
    private Collection $adminProfiles;

    #[ORM\ManyToMany(targetEntity: KitchenProfile::class, mappedBy: 'permissions')]
    private Collection $kitchenProfiles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->adminProfiles = new ArrayCollection();
        $this->kitchenProfiles = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
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
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
            $role->addPermission($this);
        }
        return $this;
    }

    public function removeRole(Role $role): static
    {
        if ($this->roles->removeElement($role)) {
            $role->removePermission($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, AdminProfile>
     */
    public function getAdminProfiles(): Collection
    {
        return $this->adminProfiles;
    }

    public function addAdminProfile(AdminProfile $adminProfile): static
    {
        if (!$this->adminProfiles->contains($adminProfile)) {
            $this->adminProfiles->add($adminProfile);
            $adminProfile->addPermission($this);
        }
        return $this;
    }

    public function removeAdminProfile(AdminProfile $adminProfile): static
    {
        if ($this->adminProfiles->removeElement($adminProfile)) {
            $adminProfile->removePermission($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, KitchenProfile>
     */
    public function getKitchenProfiles(): Collection
    {
        return $this->kitchenProfiles;
    }

    public function addKitchenProfile(KitchenProfile $kitchenProfile): static
    {
        if (!$this->kitchenProfiles->contains($kitchenProfile)) {
            $this->kitchenProfiles->add($kitchenProfile);
            $kitchenProfile->addPermission($this);
        }
        return $this;
    }

    public function removeKitchenProfile(KitchenProfile $kitchenProfile): static
    {
        if ($this->kitchenProfiles->removeElement($kitchenProfile)) {
            $kitchenProfile->removePermission($this);
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
} 