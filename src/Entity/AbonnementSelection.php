<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\AbonnementSelectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbonnementSelectionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['abonnement_selection:read']],
            denormalizationContext: ['groups' => ['abonnement_selection:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['abonnement_selection:read']],
            denormalizationContext: ['groups' => ['abonnement_selection:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['abonnement_selection:read']],
            denormalizationContext: ['groups' => ['abonnement_selection:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['abonnement_selection:read']],
    denormalizationContext: ['groups' => ['abonnement_selection:write']]
)]
class AbonnementSelection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['abonnement_selection:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Abonnement::class, inversedBy: 'selections')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?Abonnement $abonnement = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?\DateTimeInterface $dateRepas = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'])]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?string $jourSemaine = null;

    #[ORM\ManyToOne(targetEntity: Menu::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?Menu $menu = null;

    #[ORM\ManyToOne(targetEntity: Plat::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?Plat $plat = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['menu_du_jour', 'menu_normal', 'plat_individuel'])]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?string $typeSelection = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['marocain', 'italien', 'international'])]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?string $cuisineType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?string $prix = '0.00';

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['selectionne', 'confirme', 'prepare', 'livre'])]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?string $statut = 'selectionne';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['abonnement_selection:read', 'abonnement_selection:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['abonnement_selection:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['abonnement_selection:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->statut = 'selectionne';
        $this->prix = '0.00';
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

    public function getAbonnement(): ?Abonnement
    {
        return $this->abonnement;
    }

    public function setAbonnement(?Abonnement $abonnement): static
    {
        $this->abonnement = $abonnement;
        return $this;
    }

    public function getDateRepas(): ?\DateTimeInterface
    {
        return $this->dateRepas;
    }

    public function setDateRepas(\DateTimeInterface $dateRepas): static
    {
        $this->dateRepas = $dateRepas;
        return $this;
    }

    public function getJourSemaine(): ?string
    {
        return $this->jourSemaine;
    }

    public function setJourSemaine(string $jourSemaine): static
    {
        $this->jourSemaine = $jourSemaine;
        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;
        return $this;
    }

    public function getPlat(): ?Plat
    {
        return $this->plat;
    }

    public function setPlat(?Plat $plat): static
    {
        $this->plat = $plat;
        return $this;
    }

    public function getTypeSelection(): ?string
    {
        return $this->typeSelection;
    }

    public function setTypeSelection(string $typeSelection): static
    {
        $this->typeSelection = $typeSelection;
        return $this;
    }

    public function getCuisineType(): ?string
    {
        return $this->cuisineType;
    }

    public function setCuisineType(?string $cuisineType): static
    {
        $this->cuisineType = $cuisineType;
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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
     * Get selection type label
     */
    public function getTypeSelectionLabel(): string
    {
        return match($this->typeSelection) {
            'menu_du_jour' => 'Menu du Jour',
            'menu_normal' => 'Menu Normal',
            'plat_individuel' => 'Plat Individuel',
            default => 'Type inconnu'
        };
    }

    /**
     * Get status label
     */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'selectionne' => 'Sélectionné',
            'confirme' => 'Confirmé',
            'prepare' => 'En préparation',
            'livre' => 'Livré',
            default => 'Statut inconnu'
        };
    }

    /**
     * Get cuisine type label
     */
    public function getCuisineTypeLabel(): string
    {
        return match($this->cuisineType) {
            'marocain' => 'Marocain 🇲🇦',
            'italien' => 'Italien 🇮🇹',
            'international' => 'International 🌍',
            default => ''
        };
    }
} 