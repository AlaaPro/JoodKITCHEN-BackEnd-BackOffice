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
} 