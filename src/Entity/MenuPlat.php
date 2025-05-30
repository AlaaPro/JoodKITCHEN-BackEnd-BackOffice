<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\MenuPlatRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MenuPlatRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            normalizationContext: ['groups' => ['menu_plat:read']],
            denormalizationContext: ['groups' => ['menu_plat:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['menu_plat:read']],
            denormalizationContext: ['groups' => ['menu_plat:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['menu_plat:read']],
            denormalizationContext: ['groups' => ['menu_plat:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['menu_plat:read']],
    denormalizationContext: ['groups' => ['menu_plat:write']]
)]
class MenuPlat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['menu_plat:read', 'menu:read', 'plat:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'menuPlats')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['menu_plat:read', 'menu_plat:write'])]
    private ?Menu $menu = null;

    #[ORM\ManyToOne(targetEntity: Plat::class, inversedBy: 'menuPlats')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['menu_plat:read', 'menu_plat:write', 'menu:read'])]
    private ?Plat $plat = null;

    #[ORM\Column]
    #[Groups(['menu_plat:read', 'menu_plat:write', 'menu:read'])]
    private ?int $ordre = 1;

    public function __construct()
    {
        $this->ordre = 1;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPlat(): ?Plat
    {
        return $this->plat;
    }

    public function setPlat(?Plat $plat): static
    {
        $this->plat = $plat;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }
} 