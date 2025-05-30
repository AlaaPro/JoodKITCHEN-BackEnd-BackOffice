<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['commande:read']],
            denormalizationContext: ['groups' => ['commande:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['commande:read']],
            denormalizationContext: ['groups' => ['commande:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['commande:read']],
            denormalizationContext: ['groups' => ['commande:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['commande:read']],
    denormalizationContext: ['groups' => ['commande:write']]
)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['commande:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['commande:read', 'commande:write'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['commande:read', 'commande:write'])]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['commande:read', 'commande:write'])]
    private ?string $statut = 'en_attente';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['commande:read', 'commande:write'])]
    private ?string $total = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['commande:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['commande:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['commande:read', 'commande:write'])]
    private ?string $totalAvantReduction = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeArticle::class, cascade: ['persist', 'remove'])]
    #[Groups(['commande:read'])]
    private Collection $commandeArticles;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: Payment::class)]
    #[Groups(['commande:read'])]
    private Collection $payments;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeReduction::class)]
    #[Groups(['commande:read'])]
    private Collection $commandeReductions;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: FidelitePointHistory::class)]
    #[Groups(['commande:read'])]
    private Collection $fidelitePointHistories;

    public function __construct()
    {
        $this->commandeArticles = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->commandeReductions = new ArrayCollection();
        $this->fidelitePointHistories = new ArrayCollection();
        $this->dateCommande = new \DateTime();
        $this->statut = 'en_attente';
        $this->total = '0.00';
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        if (!$this->dateCommande) {
            $this->dateCommande = new \DateTime();
        }
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

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTimeInterface $dateCommande): static
    {
        $this->dateCommande = $dateCommande;
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

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
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

    public function getTotalAvantReduction(): ?string
    {
        return $this->totalAvantReduction;
    }

    public function setTotalAvantReduction(?string $totalAvantReduction): static
    {
        $this->totalAvantReduction = $totalAvantReduction;
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
            $commandeArticle->setCommande($this);
        }
        return $this;
    }

    public function removeCommandeArticle(CommandeArticle $commandeArticle): static
    {
        if ($this->commandeArticles->removeElement($commandeArticle)) {
            if ($commandeArticle->getCommande() === $this) {
                $commandeArticle->setCommande(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setCommande($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getCommande() === $this) {
                $payment->setCommande(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CommandeReduction>
     */
    public function getCommandeReductions(): Collection
    {
        return $this->commandeReductions;
    }

    public function addCommandeReduction(CommandeReduction $commandeReduction): static
    {
        if (!$this->commandeReductions->contains($commandeReduction)) {
            $this->commandeReductions->add($commandeReduction);
            $commandeReduction->setCommande($this);
        }
        return $this;
    }

    public function removeCommandeReduction(CommandeReduction $commandeReduction): static
    {
        if ($this->commandeReductions->removeElement($commandeReduction)) {
            if ($commandeReduction->getCommande() === $this) {
                $commandeReduction->setCommande(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, FidelitePointHistory>
     */
    public function getFidelitePointHistories(): Collection
    {
        return $this->fidelitePointHistories;
    }

    public function addFidelitePointHistory(FidelitePointHistory $fidelitePointHistory): static
    {
        if (!$this->fidelitePointHistories->contains($fidelitePointHistory)) {
            $this->fidelitePointHistories->add($fidelitePointHistory);
            $fidelitePointHistory->setCommande($this);
        }
        return $this;
    }

    public function removeFidelitePointHistory(FidelitePointHistory $fidelitePointHistory): static
    {
        if ($this->fidelitePointHistories->removeElement($fidelitePointHistory)) {
            if ($fidelitePointHistory->getCommande() === $this) {
                $fidelitePointHistory->setCommande(null);
            }
        }
        return $this;
    }

    /**
     * Calculate and update the total based on articles and reductions
     */
    public function calculateTotal(): static
    {
        $total = 0.0;
        
        foreach ($this->commandeArticles as $article) {
            $total += (float)$article->getPrixUnitaire() * $article->getQuantite();
        }

        $this->totalAvantReduction = number_format($total, 2, '.', '');

        // Apply reductions
        foreach ($this->commandeReductions as $reduction) {
            $total -= (float)$reduction->getValeur();
        }

        $this->total = number_format(max(0, $total), 2, '.', '');
        
        return $this;
    }

    /**
     * Get the status of the order in a human-readable format
     */
    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'en_attente' => 'En attente',
            'confirmee' => 'Confirmée',
            'en_preparation' => 'En préparation',
            'prete' => 'Prête',
            'livree' => 'Livrée',
            'annulee' => 'Annulée',
            default => 'Statut inconnu'
        };
    }
} 