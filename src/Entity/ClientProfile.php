<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\ClientProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['client_profile:read']],
            denormalizationContext: ['groups' => ['client_profile:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['client_profile:read']],
            denormalizationContext: ['groups' => ['client_profile:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['client_profile:read']],
            denormalizationContext: ['groups' => ['client_profile:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['client_profile:read']],
    denormalizationContext: ['groups' => ['client_profile:write']]
)]
class ClientProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['client_profile:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'clientProfile', targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['client_profile:read', 'client_profile:write'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['client_profile:read', 'client_profile:write', 'user:read'])]
    private ?string $adresseLivraison = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['client_profile:read', 'client_profile:write', 'user:read'])]
    private ?int $pointsFidelite = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['client_profile:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['client_profile:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'clientProfile', targetEntity: FidelitePointHistory::class)]
    #[Groups(['client_profile:read'])]
    private Collection $fidelitePointHistories;

    public function __construct()
    {
        $this->fidelitePointHistories = new ArrayCollection();
        $this->pointsFidelite = 0;
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

    public function getAdresseLivraison(): ?string
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(?string $adresseLivraison): static
    {
        $this->adresseLivraison = $adresseLivraison;
        return $this;
    }

    public function getPointsFidelite(): ?int
    {
        return $this->pointsFidelite;
    }

    public function setPointsFidelite(int $pointsFidelite): static
    {
        $this->pointsFidelite = $pointsFidelite;
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
            $fidelitePointHistory->setClientProfile($this);
        }
        return $this;
    }

    public function removeFidelitePointHistory(FidelitePointHistory $fidelitePointHistory): static
    {
        if ($this->fidelitePointHistories->removeElement($fidelitePointHistory)) {
            if ($fidelitePointHistory->getClientProfile() === $this) {
                $fidelitePointHistory->setClientProfile(null);
            }
        }
        return $this;
    }

    /**
     * Add points to the client's fidelity points
     */
    public function addPoints(int $points): static
    {
        $this->pointsFidelite += $points;
        return $this;
    }

    /**
     * Remove points from the client's fidelity points
     */
    public function removePoints(int $points): static
    {
        $this->pointsFidelite = max(0, $this->pointsFidelite - $points);
        return $this;
    }
} 