<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['category:read']],
            denormalizationContext: ['groups' => ['category:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['category:read']],
            denormalizationContext: ['groups' => ['category:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['category:read']],
            denormalizationContext: ['groups' => ['category:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['category:read']],
    denormalizationContext: ['groups' => ['category:write']]
)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['category:read', 'plat:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['category:read', 'category:write', 'plat:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['category:read', 'category:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['category:read', 'category:write'])]
    private ?string $icon = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'Color must be a valid hex color')]
    #[Groups(['category:read', 'category:write'])]
    private ?string $couleur = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['category:read', 'category:write'])]
    private ?int $position = 0;

    #[ORM\Column]
    #[Groups(['category:read', 'category:write'])]
    private ?bool $actif = true;

    #[ORM\Column]
    #[Groups(['category:read', 'category:write'])]
    private ?bool $visible = true;

    // Self-referencing for hierarchy
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'sousCategories')]
    #[Groups(['category:read', 'category:write'])]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[Groups(['category:read'])]
    private Collection $sousCategories;

    // Relations
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Plat::class)]
    #[Groups(['category:read'])]
    private Collection $plats;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['category:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['category:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->sousCategories = new ArrayCollection();
        $this->plats = new ArrayCollection();
        $this->position = 0;
        $this->actif = true;
        $this->visible = true;
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

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getSousCategories(): Collection
    {
        return $this->sousCategories;
    }

    public function addSousCategory(self $sousCategory): static
    {
        if (!$this->sousCategories->contains($sousCategory)) {
            $this->sousCategories->add($sousCategory);
            $sousCategory->setParent($this);
        }
        return $this;
    }

    public function removeSousCategory(self $sousCategory): static
    {
        if ($this->sousCategories->removeElement($sousCategory)) {
            if ($sousCategory->getParent() === $this) {
                $sousCategory->setParent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Plat>
     */
    public function getPlats(): Collection
    {
        return $this->plats;
    }

    public function addPlat(Plat $plat): static
    {
        if (!$this->plats->contains($plat)) {
            $this->plats->add($plat);
            $plat->setCategory($this);
        }
        return $this;
    }

    public function removePlat(Plat $plat): static
    {
        if ($this->plats->removeElement($plat)) {
            if ($plat->getCategory() === $this) {
                $plat->setCategory(null);
            }
        }
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
     * Count total dishes in this category and subcategories
     */
    public function getTotalPlatsCount(): int
    {
        $count = $this->plats->count();
        
        foreach ($this->sousCategories as $sousCategory) {
            $count += $sousCategory->getTotalPlatsCount();
        }
        
        return $count;
    }

    /**
     * Check if this is a root category
     */
    public function isRootCategory(): bool
    {
        return $this->parent === null;
    }

    /**
     * Get full path (Parent > Child)
     */
    public function getFullPath(): string
    {
        $path = $this->nom;
        $parent = $this->parent;
        
        while ($parent !== null) {
            $path = $parent->getNom() . ' > ' . $path;
            $parent = $parent->getParent();
        }
        
        return $path;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
} 