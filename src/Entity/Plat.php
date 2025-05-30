<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\PlatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlatRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['plat:read']],
            denormalizationContext: ['groups' => ['plat:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['plat:read']],
            denormalizationContext: ['groups' => ['plat:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['plat:read']],
            denormalizationContext: ['groups' => ['plat:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['plat:read']],
    denormalizationContext: ['groups' => ['plat:write']]
)]
class Plat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['plat:read', 'menu:read', 'commande_article:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['plat:read', 'plat:write', 'menu:read', 'commande_article:read'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plat:read', 'plat:write', 'menu:read'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['plat:read', 'plat:write', 'menu:read', 'commande_article:read'])]
    private ?string $prix = '0.00';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['plat:read', 'plat:write', 'menu:read'])]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plat:read', 'plat:write', 'menu:read'])]
    private ?string $image = null;

    #[ORM\Column]
    #[Groups(['plat:read', 'plat:write', 'menu:read'])]
    private ?bool $disponible = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plat:read', 'plat:write', 'menu:read'])]
    private ?string $allergenes = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['plat:read', 'plat:write', 'menu:read'])]
    private ?int $tempsPreparation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['plat:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['plat:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'plat', targetEntity: MenuPlat::class)]
    #[Groups(['plat:read'])]
    private Collection $menuPlats;

    #[ORM\OneToMany(mappedBy: 'plat', targetEntity: CommandeArticle::class)]
    #[Groups(['plat:read'])]
    private Collection $commandeArticles;

    public function __construct()
    {
        $this->menuPlats = new ArrayCollection();
        $this->commandeArticles = new ArrayCollection();
        $this->prix = '0.00';
        $this->disponible = true;
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

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;
        return $this;
    }

    public function getAllergenes(): ?string
    {
        return $this->allergenes;
    }

    public function setAllergenes(?string $allergenes): static
    {
        $this->allergenes = $allergenes;
        return $this;
    }

    public function getTempsPreparation(): ?int
    {
        return $this->tempsPreparation;
    }

    public function setTempsPreparation(?int $tempsPreparation): static
    {
        $this->tempsPreparation = $tempsPreparation;
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
     * @return Collection<int, MenuPlat>
     */
    public function getMenuPlats(): Collection
    {
        return $this->menuPlats;
    }

    public function addMenuPlat(MenuPlat $menuPlat): static
    {
        if (!$this->menuPlats->contains($menuPlat)) {
            $this->menuPlats->add($menuPlat);
            $menuPlat->setPlat($this);
        }
        return $this;
    }

    public function removeMenuPlat(MenuPlat $menuPlat): static
    {
        if ($this->menuPlats->removeElement($menuPlat)) {
            if ($menuPlat->getPlat() === $this) {
                $menuPlat->setPlat(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CommandeArticle>
     */
    public function getCommandeArticles(): Collection
    {
        return $this->commandeArticles;
    }

    public function addCommandeArticle(CommandeArticle $commandeArticle): static
    {
        if (!$this->commandeArticles->contains($commandeArticle)) {
            $this->commandeArticles->add($commandeArticle);
            $commandeArticle->setPlat($this);
        }
        return $this;
    }

    public function removeCommandeArticle(CommandeArticle $commandeArticle): static
    {
        if ($this->commandeArticles->removeElement($commandeArticle)) {
            if ($commandeArticle->getPlat() === $this) {
                $commandeArticle->setPlat(null);
            }
        }
        return $this;
    }
} 