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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    // Enhanced kitchen-specific fields (following AdminProfile pattern)
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?array $specialites = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?array $certifications = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?array $horaireTravail = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?array $permissionsKitchen = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?string $notesInterne = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?string $statutTravail = 'actif'; // actif, pause, absent, conge

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?int $experienceAnnees = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?string $salaireHoraire = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?int $heuresParSemaine = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['kitchen_profile:read', 'kitchen_profile:write', 'user:read'])]
    private ?\DateTimeInterface $dateEmbauche = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['kitchen_profile:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['kitchen_profile:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    // New normalized relationships (following AdminProfile pattern)
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'kitchenProfiles')]
    #[ORM\JoinTable(name: 'kitchen_profile_roles')]
    #[Groups(['kitchen_profile:read'])]
    private Collection $roles;

    #[ORM\ManyToMany(targetEntity: Permission::class, inversedBy: 'kitchenProfiles')]
    #[ORM\JoinTable(name: 'kitchen_profile_permissions')]
    #[Groups(['kitchen_profile:read'])]
    private Collection $permissions;

    public function __construct()
    {
        $this->specialites = [];
        $this->roles = new ArrayCollection();
        $this->permissions = new ArrayCollection();
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

    // Kitchen-specific methods (enhanced following AdminProfile pattern)
    public function getSpecialites(): array
    {
        return $this->specialites ?? [];
    }

    public function setSpecialites(?array $specialites): static
    {
        $this->specialites = $specialites ?? [];
        return $this;
    }

    public function addSpecialite(string $specialite): static
    {
        if ($this->specialites === null) {
            $this->specialites = [];
        }
        if (!in_array($specialite, $this->specialites)) {
            $this->specialites[] = $specialite;
        }
        return $this;
    }

    public function removeSpecialite(string $specialite): static
    {
        if ($this->specialites !== null) {
            $this->specialites = array_values(array_filter(
                $this->specialites,
                fn($s) => $s !== $specialite
            ));
        }
        return $this;
    }

    public function hasSpecialite(string $specialite): bool
    {
        return $this->specialites !== null && in_array($specialite, $this->specialites);
    }

    public function getCertifications(): ?array
    {
        return $this->certifications;
    }

    public function setCertifications(?array $certifications): static
    {
        $this->certifications = $certifications;
        return $this;
    }

    public function getHoraireTravail(): ?array
    {
        return $this->horaireTravail;
    }

    public function setHoraireTravail(?array $horaireTravail): static
    {
        $this->horaireTravail = $horaireTravail;
        return $this;
    }

    public function getPermissionsKitchen(): ?array
    {
        return $this->permissionsKitchen;
    }

    public function setPermissionsKitchen(?array $permissionsKitchen): static
    {
        $this->permissionsKitchen = $permissionsKitchen;
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

    public function getStatutTravail(): ?string
    {
        return $this->statutTravail;
    }

    public function setStatutTravail(?string $statutTravail): static
    {
        $this->statutTravail = $statutTravail;
        return $this;
    }

    public function getExperienceAnnees(): ?int
    {
        return $this->experienceAnnees;
    }

    public function setExperienceAnnees(?int $experienceAnnees): static
    {
        $this->experienceAnnees = $experienceAnnees;
        return $this;
    }

    public function getSalaireHoraire(): ?string
    {
        return $this->salaireHoraire;
    }

    public function setSalaireHoraire(?string $salaireHoraire): static
    {
        $this->salaireHoraire = $salaireHoraire;
        return $this;
    }

    public function getHeuresParSemaine(): ?int
    {
        return $this->heuresParSemaine;
    }

    public function setHeuresParSemaine(?int $heuresParSemaine): static
    {
        $this->heuresParSemaine = $heuresParSemaine;
        return $this;
    }

    public function getDateEmbauche(): ?\DateTimeInterface
    {
        return $this->dateEmbauche;
    }

    public function setDateEmbauche(?\DateTimeInterface $dateEmbauche): static
    {
        $this->dateEmbauche = $dateEmbauche;
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

    // === NORMALIZED PERMISSION METHODS (following AdminProfile pattern) ===

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
        }
        return $this;
    }

    public function removeRole(Role $role): static
    {
        $this->roles->removeElement($role);
        return $this;
    }

    public function hasRole(Role $role): bool
    {
        return $this->roles->contains($role);
    }

    public function hasRoleByName(string $roleName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getName() === $roleName) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }
        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        $this->permissions->removeElement($permission);
        return $this;
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->permissions->contains($permission);
    }

    public function hasPermissionByName(string $permissionName): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->getName() === $permissionName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all permissions (from direct assignment and role inheritance)
     */
    public function getAllPermissions(): array
    {
        $permissions = [];
        
        // Direct permissions
        foreach ($this->permissions as $permission) {
            $permissions[$permission->getName()] = $permission;
        }
        
        // Permissions from roles
        foreach ($this->roles as $role) {
            foreach ($role->getPermissions() as $permission) {
                $permissions[$permission->getName()] = $permission;
            }
        }
        
        // Legacy JSON permissions for backward compatibility
        if ($this->permissionsKitchen) {
            foreach ($this->permissionsKitchen as $permissionName) {
                if (!isset($permissions[$permissionName])) {
                    $permissions[$permissionName] = $permissionName; // String fallback
                }
            }
        }
        
        return array_values($permissions);
    }

    /**
     * Get all permission names as array
     */
    public function getAllPermissionNames(): array
    {
        $permissions = $this->getAllPermissions();
        return array_map(function($permission) {
            return is_string($permission) ? $permission : $permission->getName();
        }, $permissions);
    }

    // === KITCHEN-SPECIFIC HELPER METHODS ===

    /**
     * Check if staff is currently available for work
     */
    public function isAvailable(): bool
    {
        return $this->statutTravail === 'actif';
    }

    /**
     * Check if staff can work on specific cuisine
     */
    public function canWorkCuisine(string $cuisineType): bool
    {
        return $this->hasSpecialite($cuisineType) || $this->hasSpecialite('polyvalent');
    }

    /**
     * Get formatted experience string
     */
    public function getExperienceFormatted(): string
    {
        if (!$this->experienceAnnees) {
            return 'Débutant';
        }
        
        return $this->experienceAnnees === 1 
            ? '1 an' 
            : $this->experienceAnnees . ' ans';
    }

    /**
     * Get status badge color for UI
     */
    public function getStatutColor(): string
    {
        return match($this->statutTravail) {
            'actif' => 'success',
            'pause' => 'warning',
            'absent' => 'danger',
            'conge' => 'info',
            default => 'secondary'
        };
    }

    /**
     * Get position badge color for UI
     */
    public function getPositionColor(): string
    {
        return match($this->posteCuisine) {
            'chef_executif' => 'danger',
            'chef_cuisine' => 'warning',
            'sous_chef' => 'info',
            'cuisinier' => 'primary',
            'commis' => 'success',
            'plongeur' => 'secondary',
            default => 'secondary'
        };
    }
} 