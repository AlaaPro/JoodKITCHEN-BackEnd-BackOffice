<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\CommandeArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommandeArticleRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['commande_article:read']],
            denormalizationContext: ['groups' => ['commande_article:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['commande_article:read']],
            denormalizationContext: ['groups' => ['commande_article:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['commande_article:read']],
            denormalizationContext: ['groups' => ['commande_article:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['commande_article:read']],
    denormalizationContext: ['groups' => ['commande_article:write']]
)]
class CommandeArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['commande_article:read', 'commande:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'commandeArticles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['commande_article:read', 'commande_article:write'])]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: Plat::class, inversedBy: 'commandeArticles')]
    #[Groups(['commande_article:read', 'commande_article:write', 'commande:read'])]
    private ?Plat $plat = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'commandeArticles')]
    #[Groups(['commande_article:read', 'commande_article:write', 'commande:read'])]
    private ?Menu $menu = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    #[Groups(['commande_article:read', 'commande_article:write', 'commande:read'])]
    private ?int $quantite = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['commande_article:read', 'commande_article:write', 'commande:read'])]
    private ?string $prixUnitaire = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['commande_article:read', 'commande_article:write', 'commande:read'])]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['commande_article:read', 'commande:read'])]
    private ?string $nomOriginal = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['commande_article:read', 'commande:read'])]
    private ?string $descriptionOriginale = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['commande_article:read'])]
    private ?\DateTimeInterface $dateSnapshot = null;

    public function __construct()
    {
        $this->quantite = 1;
        $this->prixUnitaire = '0.00';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
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

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrixUnitaire(): ?string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    /**
     * Calculate the total price for this article (quantity * unit price)
     */
    public function getTotalPrice(): string
    {
        return number_format((float)$this->prixUnitaire * $this->quantite, 2, '.', '');
    }

    public function getNomOriginal(): ?string
    {
        return $this->nomOriginal;
    }

    public function setNomOriginal(?string $nomOriginal): static
    {
        $this->nomOriginal = $nomOriginal;
        return $this;
    }

    public function getDescriptionOriginale(): ?string
    {
        return $this->descriptionOriginale;
    }

    public function setDescriptionOriginale(?string $descriptionOriginale): static
    {
        $this->descriptionOriginale = $descriptionOriginale;
        return $this;
    }

    public function getDateSnapshot(): ?\DateTimeInterface
    {
        return $this->dateSnapshot;
    }

    public function setDateSnapshot(?\DateTimeInterface $dateSnapshot): static
    {
        $this->dateSnapshot = $dateSnapshot;
        return $this;
    }

    /**
     * Get the display name for this article (original name if available, current name if still exists, or fallback)
     */
    public function getDisplayName(): string
    {
        // Priority: Original name > Current item name > Fallback
        if ($this->nomOriginal) {
            return $this->nomOriginal;
        }
        
        // Check if it's a plat (individual dish)
        if ($this->plat) {
            return $this->plat->getNom();
        }
        
        // Check if it's a menu (daily menu, package, etc.)
        if ($this->menu) {
            return $this->menu->getNom();
        }
        
        return '🗑️ Article supprimé';
    }

    /**
     * Check if this article references a deleted item
     */
    public function isDeleted(): bool
    {
        // Item is deleted if both plat and menu are null
        return $this->plat === null && $this->menu === null;
    }

    /**
     * Get the item type for this article
     */
    public function getItemType(): string
    {
        if ($this->plat) {
            return 'plat';
        }
        
        if ($this->menu) {
            return 'menu';
        }
        
        return 'deleted';
    }

    /**
     * Get the current item entity (plat or menu)
     */
    public function getCurrentItem(): ?object
    {
        return $this->plat ?? $this->menu;
    }

    /**
     * Get comprehensive item information for display
     */
    public function getItemInfo(): array
    {
        $item = $this->getCurrentItem();
        
        return [
            'id' => $this->getId(),
            'name' => $this->getDisplayName(),
            'type' => $this->getItemType(),
            'isDeleted' => $this->isDeleted(),
            'originalName' => $this->getNomOriginal(),
            'description' => $this->getDescriptionOriginale(),
            'snapshotDate' => $this->getDateSnapshot()?->format('d/m/Y H:i'),
            'currentItem' => $item ? [
                'id' => $item->getId(),
                'name' => $item->getNom(),
                'type' => $this->getItemType()
            ] : null,
            'quantite' => $this->getQuantite(),
            'prixUnitaire' => $this->getPrixUnitaire(),
            'total' => $this->getQuantite() * $this->getPrixUnitaire(),
            'commentaire' => $this->getCommentaire()
        ];
    }
} 