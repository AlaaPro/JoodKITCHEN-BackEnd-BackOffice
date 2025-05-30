<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['menu:read']],
            denormalizationContext: ['groups' => ['menu:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['menu:read']],
            denormalizationContext: ['groups' => ['menu:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['menu:read']],
            denormalizationContext: ['groups' => ['menu:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['menu:read']],
    denormalizationContext: ['groups' => ['menu:write']]
)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['menu:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['normal', 'menu_du_jour'])]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $type = 'normal';

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $jourSemaine = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['menu:read', 'menu:write'])]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $prix = '0.00';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['menu:read', 'menu:write'])]
    private ?string $tag = null;

    #[ORM\Column]
    #[Groups(['menu:read', 'menu:write'])]
    private ?bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['menu:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['menu:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'menu', targetEntity: MenuPlat::class, cascade: ['persist', 'remove'])]
    #[Groups(['menu:read'])]
    private Collection $menuPlats;

    #[ORM\OneToMany(mappedBy: 'menu', targetEntity: CommandeArticle::class)]
    #[Groups(['menu:read'])]
    private Collection $commandeArticles;

    public function __construct()
    {
        $this->menuPlats = new ArrayCollection();
        $this->commandeArticles = new ArrayCollection();
        $this->type = 'normal';
        $this->actif = true;
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getJourSemaine(): ?string
    {
        return $this->jourSemaine;
    }

    public function setJourSemaine(?string $jourSemaine): static
    {
        $this->jourSemaine = $jourSemaine;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;
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

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    public function getActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
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
     * @return Collection<int, MenuPlat>
     */
    public function getMenuPlats(): Collection
    {
        return $this->menuPlats;
    }

    public function addMenuPlat(MenuPlat $menuPlat): static
    {
        if (!$this->menuPlats->contains($menuPlat)) {
            $this->menuPlats->add($menuPlat);
            $menuPlat->setMenu($this);
        }
        return $this;
    }

    public function removeMenuPlat(MenuPlat $menuPlat): static
    {
        if ($this->menuPlats->removeElement($menuPlat)) {
            if ($menuPlat->getMenu() === $this) {
                $menuPlat->setMenu(null);
            }
        }
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
            $commandeArticle->setMenu($this);
        }
        return $this;
    }

    public function removeCommandeArticle(CommandeArticle $commandeArticle): static
    {
        if ($this->commandeArticles->removeElement($commandeArticle)) {
            if ($commandeArticle->getMenu() === $this) {
                $commandeArticle->setMenu(null);
            }
        }
        return $this;
    }
} 