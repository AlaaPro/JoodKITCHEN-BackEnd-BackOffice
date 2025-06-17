<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\AbonnementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbonnementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['abonnement:read']],
            denormalizationContext: ['groups' => ['abonnement:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['abonnement:read']],
            denormalizationContext: ['groups' => ['abonnement:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['abonnement:read']],
            denormalizationContext: ['groups' => ['abonnement:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['abonnement:read']],
    denormalizationContext: ['groups' => ['abonnement:write']]
)]
class Abonnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['abonnement:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'abonnements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['abonnement:read', 'abonnement:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['hebdo', 'mensuel'])]
    #[Groups(['abonnement:read', 'abonnement:write'])]
    private ?string $type = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    #[Groups(['abonnement:read', 'abonnement:write'])]
    private ?int $repasParJour = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    #[Groups(['abonnement:read', 'abonnement:write'])]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    #[Groups(['abonnement:read', 'abonnement:write'])]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['actif', 'suspendu', 'expire', 'annule'])]
    #[Groups(['abonnement:read', 'abonnement:write'])]
    private ?string $statut = 'actif';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['abonnement:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['abonnement:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'abonnement', targetEntity: AbonnementSelection::class, cascade: ['persist', 'remove'])]
    #[Groups(['abonnement:read'])]
    private Collection $selections;

    public function __construct()
    {
        $this->statut = 'actif';
        $this->selections = new ArrayCollection();
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getRepasParJour(): ?int
    {
        return $this->repasParJour;
    }

    public function setRepasParJour(int $repasParJour): static
    {
        $this->repasParJour = $repasParJour;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
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
     * Check if the subscription is currently active
     */
    public function isActive(): bool
    {
        $now = new \DateTime();
        return $this->statut === 'actif' 
            && $this->dateDebut <= $now 
            && $this->dateFin >= $now;
    }

    /**
     * Check if the subscription has expired
     */
    public function isExpired(): bool
    {
        $now = new \DateTime();
        return $this->dateFin < $now;
    }

    /**
     * Get the number of days remaining in the subscription
     */
    public function getDaysRemaining(): int
    {
        $now = new \DateTime();
        if ($this->dateFin < $now) {
            return 0;
        }
        return $now->diff($this->dateFin)->days;
    }

    /**
     * Get subscription type label
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'hebdo' => 'Hebdomadaire',
            'mensuel' => 'Mensuel',
            default => 'Type inconnu'
        };
    }

    /**
     * Get status label
     */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'actif' => 'Actif',
            'suspendu' => 'Suspendu',
            'expire' => 'Expiré',
            'annule' => 'Annulé',
            default => 'Statut inconnu'
        };
    }

    /**
     * @return Collection<int, AbonnementSelection>
     */
    public function getSelections(): Collection
    {
        return $this->selections;
    }

    public function addSelection(AbonnementSelection $selection): static
    {
        if (!$this->selections->contains($selection)) {
            $this->selections->add($selection);
            $selection->setAbonnement($this);
        }

        return $this;
    }

    public function removeSelection(AbonnementSelection $selection): static
    {
        if ($this->selections->removeElement($selection)) {
            // set the owning side to null (unless already changed)
            if ($selection->getAbonnement() === $this) {
                $selection->setAbonnement(null);
            }
        }

        return $this;
    }

    /**
     * Get weekly discount rate (to be defined later)
     */
    public function getWeeklyDiscountRate(): float
    {
        // This will be configured later by admin
        // For now, return a default 10% discount for weekly subscriptions
        if ($this->type === 'hebdo') {
            return 0.10; // 10% discount
        }
        return 0.0;
    }

    /**
     * Calculate total weekly price with discount
     */
    public function calculateWeeklyPrice(): float
    {
        $totalPrice = 0.0;
        
        foreach ($this->selections as $selection) {
            $totalPrice += (float) $selection->getPrix();
        }
        
        $discountRate = $this->getWeeklyDiscountRate();
        return $totalPrice * (1 - $discountRate);
    }

    /**
     * Check if subscription requires CMI payment setup
     */
    public function requiresCMIPayment(): bool
    {
        // Check if user hasn't completed payment setup
        // This will be implemented when integrating with CMI
        return true; // For now, assume all subscriptions need payment setup
    }
} 