<?php

namespace App\Entity;

use App\Repository\OrderStatusHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderStatusHistoryRepository::class)]
#[ORM\Table(name: 'order_status_history')]
#[ORM\Index(name: 'idx_order_status', columns: ['commande_id', 'status'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
class OrderStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order_status_history:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['order_status_history:read'])]
    private ?Commande $commande = null;

    #[ORM\Column(length: 50)]
    #[Groups(['order_status_history:read'])]
    private ?string $status = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['order_status_history:read'])]
    private ?string $previousStatus = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['order_status_history:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order_status_history:read'])]
    private ?User $changedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['order_status_history:read'])]
    private ?string $comment = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPreviousStatus(): ?string
    {
        return $this->previousStatus;
    }

    public function setPreviousStatus(?string $previousStatus): static
    {
        $this->previousStatus = $previousStatus;
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

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): static
    {
        $this->changedBy = $changedBy;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Get the OrderStatus enum from the string value
     */
    public function getStatusEnum(): ?\App\Enum\OrderStatus
    {
        try {
            return \App\Enum\OrderStatus::from($this->status);
        } catch (\ValueError $e) {
            return null;
        }
    }

    /**
     * Get the previous OrderStatus enum from the string value
     */
    public function getPreviousStatusEnum(): ?\App\Enum\OrderStatus
    {
        if (!$this->previousStatus) {
            return null;
        }
        
        try {
            return \App\Enum\OrderStatus::from($this->previousStatus);
        } catch (\ValueError $e) {
            return null;
        }
    }
} 