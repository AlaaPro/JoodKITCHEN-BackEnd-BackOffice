<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\FidelitePointHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FidelitePointHistoryRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['fidelite_point_history:read']],
            denormalizationContext: ['groups' => ['fidelite_point_history:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['fidelite_point_history:read']],
            denormalizationContext: ['groups' => ['fidelite_point_history:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['fidelite_point_history:read']],
            denormalizationContext: ['groups' => ['fidelite_point_history:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['fidelite_point_history:read']],
    denormalizationContext: ['groups' => ['fidelite_point_history:write']]
)]
class FidelitePointHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['fidelite_point_history:read', 'client_profile:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClientProfile::class, inversedBy: 'fidelitePointHistories')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['fidelite_point_history:read', 'fidelite_point_history:write'])]
    private ?ClientProfile $clientProfile = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['gain', 'depense'])]
    #[Groups(['fidelite_point_history:read', 'fidelite_point_history:write', 'client_profile:read'])]
    private ?string $type = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Groups(['fidelite_point_history:read', 'fidelite_point_history:write', 'client_profile:read'])]
    private ?int $points = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['fidelite_point_history:read', 'fidelite_point_history:write', 'client_profile:read'])]
    private ?string $source = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'fidelitePointHistories')]
    #[Groups(['fidelite_point_history:read', 'fidelite_point_history:write'])]
    private ?Commande $commande = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['fidelite_point_history:read', 'fidelite_point_history:write', 'client_profile:read'])]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['fidelite_point_history:read', 'fidelite_point_history:write', 'client_profile:read'])]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientProfile(): ?ClientProfile
    {
        return $this->clientProfile;
    }

    public function setClientProfile(?ClientProfile $clientProfile): static
    {
        $this->clientProfile = $clientProfile;
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

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;
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

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
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