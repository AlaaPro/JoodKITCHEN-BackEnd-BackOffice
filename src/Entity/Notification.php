<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['notification:read']],
            denormalizationContext: ['groups' => ['notification:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['notification:read']],
            denormalizationContext: ['groups' => ['notification:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['notification:read']],
            denormalizationContext: ['groups' => ['notification:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['notification:read']],
    denormalizationContext: ['groups' => ['notification:write']]
)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['notification:read', 'notification:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['notification:read', 'notification:write', 'user:read'])]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['notification:read', 'notification:write', 'user:read'])]
    private ?string $message = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['info', 'success', 'warning', 'error', 'commande', 'promotion'])]
    #[Groups(['notification:read', 'notification:write', 'user:read'])]
    private ?string $type = 'info';

    #[ORM\Column]
    #[Groups(['notification:read', 'notification:write', 'user:read'])]
    private ?bool $lu = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['notification:read', 'notification:write', 'user:read'])]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:write', 'user:read'])]
    private ?\DateTimeInterface $dateLecture = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:read', 'notification:write'])]
    private ?array $metadonnees = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['notification:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['notification:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->type = 'info';
        $this->lu = false;
        $this->dateEnvoi = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        if (!$this->dateEnvoi) {
            $this->dateEnvoi = new \DateTime();
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

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
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

    public function getLu(): ?bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): static
    {
        $this->lu = $lu;
        if ($lu && !$this->dateLecture) {
            $this->dateLecture = new \DateTime();
        }
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getDateLecture(): ?\DateTimeInterface
    {
        return $this->dateLecture;
    }

    public function setDateLecture(?\DateTimeInterface $dateLecture): static
    {
        $this->dateLecture = $dateLecture;
        return $this;
    }

    public function getMetadonnees(): ?array
    {
        return $this->metadonnees;
    }

    public function setMetadonnees(?array $metadonnees): static
    {
        $this->metadonnees = $metadonnees;
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
     * Mark notification as read
     */
    public function markAsRead(): static
    {
        $this->lu = true;
        $this->dateLecture = new \DateTime();
        return $this;
    }

    /**
     * Check if notification is recent (less than 24 hours)
     */
    public function isRecent(): bool
    {
        $now = new \DateTime();
        $interval = $now->diff($this->dateEnvoi);
        return $interval->days < 1;
    }
} 