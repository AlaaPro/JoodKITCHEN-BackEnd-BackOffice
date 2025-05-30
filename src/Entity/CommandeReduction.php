<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\CommandeReductionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommandeReductionRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['commande_reduction:read']],
            denormalizationContext: ['groups' => ['commande_reduction:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['commande_reduction:read']],
            denormalizationContext: ['groups' => ['commande_reduction:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['commande_reduction:read']],
            denormalizationContext: ['groups' => ['commande_reduction:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['commande_reduction:read']],
    denormalizationContext: ['groups' => ['commande_reduction:write']]
)]
class CommandeReduction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['commande_reduction:read', 'commande:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'commandeReductions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['commande_reduction:read', 'commande_reduction:write'])]
    private ?Commande $commande = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['pourcentage', 'montant_fixe', 'points_fidelite'])]
    #[Groups(['commande_reduction:read', 'commande_reduction:write', 'commande:read'])]
    private ?string $type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['commande_reduction:read', 'commande_reduction:write', 'commande:read'])]
    private ?string $valeur = '0.00';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['commande_reduction:read', 'commande_reduction:write', 'commande:read'])]
    private ?string $source = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['commande_reduction:read', 'commande_reduction:write', 'commande:read'])]
    private ?string $codePromo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['commande_reduction:read', 'commande_reduction:write'])]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->valeur = '0.00';
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getCodePromo(): ?string
    {
        return $this->codePromo;
    }

    public function setCodePromo(?string $codePromo): static
    {
        $this->codePromo = $codePromo;
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
} 