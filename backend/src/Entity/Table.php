<?php

namespace App\Entity;

use App\Repository\TableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TableRepository::class)]
#[ORM\Table(name: '`table`')]
#[UniqueEntity(fields: ['qrCode'], message: 'This QR code is already in use.')]
#[UniqueEntity(fields: ['tableNumber', 'restaurant'], message: 'This table number already exists in this restaurant.')]
class Table
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    private ?string $tableNumber = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    #[Assert\Length(max: 255)]
    private ?string $qrCode = null;

    #[ORM\ManyToOne(inversedBy: 'tables')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Restaurant $restaurant = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $updatedAt = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'table')]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTableNumber(): ?string
    {
        return $this->tableNumber;
    }

    public function setTableNumber(string $tableNumber): static
    {
        $this->tableNumber = $tableNumber;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(?string $qrCode): static
    {
        $this->qrCode = $qrCode;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function setRestaurant(?Restaurant $restaurant): static
    {
        $this->restaurant = $restaurant;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setTable($this);
        }
        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getTable() === $this) {
                $order->setTable(null);
            }
        }
        return $this;
    }

    // Business logic methods
    public function generateQrCode(): string
    {
        if (!$this->restaurant) {
            throw new \LogicException('Cannot generate QR code without restaurant assignment');
        }
        
        // Generate a unique QR code based on restaurant ID and table number
        $qrData = sprintf('%s-%s-%s', 
            $this->restaurant->getId(),
            $this->tableNumber,
            uniqid()
        );
        
        $this->qrCode = base64_encode($qrData);
        $this->updatedAt = new \DateTime();
        
        return $this->qrCode;
    }

    public function getQrCodeUrl(): ?string
    {
        if (!$this->qrCode) {
            return null;
        }
        
        // Generate the URL that customers will visit when scanning QR code
        return sprintf('/api/public/menu/%s', $this->qrCode);
    }

    public function getFullTableIdentifier(): string
    {
        return sprintf('Table %s', $this->tableNumber);
    }

    public function hasActiveOrders(): bool
    {
        foreach ($this->orders as $order) {
            if (!$order->isServed() && !$order->isCancelled()) {
                return true;
            }
        }
        return false;
    }

    public function getActiveOrders(): Collection
    {
        return $this->orders->filter(function (Order $order) {
            return !$order->isServed() && !$order->isCancelled();
        });
    }

    public function getTotalOrdersToday(): int
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        return $this->orders->filter(function (Order $order) use ($today, $tomorrow) {
            return $order->getCreatedAt() >= $today && $order->getCreatedAt() < $tomorrow;
        })->count();
    }
}
