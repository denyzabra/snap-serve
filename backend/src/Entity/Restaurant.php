<?php

namespace App\Entity;

use App\Repository\RestaurantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: RestaurantRepository::class)]
#[ORM\Table(name: 'restaurant')]
#[UniqueEntity(fields: ['slug'], message: 'This restaurant slug is already taken.')]
class Restaurant
{
    // Service type constants
    public const SERVICE_DINE_IN = 'dine_in';
    public const SERVICE_TAKEOUT = 'takeout';
    public const SERVICE_DELIVERY = 'delivery';

    // Cuisine type constants
    public const CUISINE_ITALIAN = 'italian';
    public const CUISINE_CHINESE = 'chinese';
    public const CUISINE_INDIAN = 'indian';
    public const CUISINE_MEXICAN = 'mexican';
    public const CUISINE_AMERICAN = 'american';
    public const CUISINE_FAST_FOOD = 'fast_food';
    public const CUISINE_CAFE = 'cafe';
    public const CUISINE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $state = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $country = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $logoUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $coverImageUrl = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: [
        self::CUISINE_ITALIAN, self::CUISINE_CHINESE, self::CUISINE_INDIAN,
        self::CUISINE_MEXICAN, self::CUISINE_AMERICAN, self::CUISINE_FAST_FOOD,
        self::CUISINE_CAFE, self::CUISINE_OTHER
    ])]
    private ?string $cuisineType = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $serviceTypes = [];

    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Primary color must be a valid hex color')]
    private ?string $primaryColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Secondary color must be a valid hex color')]
    private ?string $secondaryColor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialInstructions = null;

    #[ORM\Column(nullable: true)]
    private ?bool $acceptsReservations = false;

    #[ORM\Column(nullable: true)]
    private ?bool $hasDelivery = false;

    #[ORM\Column(nullable: true)]
    private ?bool $hasTakeout = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $minimumOrderAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $deliveryFee = null;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedDeliveryTime = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?bool $isVerified = false;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $updatedAt = null;

    /**
     * @var Collection<int, Table>
     */
    #[ORM\OneToMany(targetEntity: Table::class, mappedBy: 'restaurant')]
    private Collection $tables;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'restaurant')]
    private Collection $categories;

    /**
     * @var Collection<int, MenuItem>
     */
    #[ORM\OneToMany(targetEntity: MenuItem::class, mappedBy: 'restaurant')]
    private Collection $menuItems;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'restaurant')]
    private Collection $orders;

    /**
     * @var Collection<int, BusinessHours>
     */
    #[ORM\OneToMany(targetEntity: BusinessHours::class, mappedBy: 'restaurant', cascade: ['persist', 'remove'])]
    private Collection $businessHours;

    public function __construct()
    {
        $this->tables = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->menuItems = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->businessHours = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isActive = true;
        $this->isVerified = false;
        $this->serviceTypes = [self::SERVICE_DINE_IN];
        $this->hasTakeout = true;
        $this->acceptsReservations = false;
        $this->hasDelivery = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->slug = $this->generateSlug($name);
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCoverImageUrl(): ?string
    {
        return $this->coverImageUrl;
    }

    public function setCoverImageUrl(?string $coverImageUrl): static
    {
        $this->coverImageUrl = $coverImageUrl;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCuisineType(): ?string
    {
        return $this->cuisineType;
    }

    public function setCuisineType(?string $cuisineType): static
    {
        $this->cuisineType = $cuisineType;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getServiceTypes(): ?array
    {
        return $this->serviceTypes ?? [];
    }

    public function setServiceTypes(?array $serviceTypes): static
    {
        $this->serviceTypes = $serviceTypes;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getPrimaryColor(): ?string
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(?string $primaryColor): static
    {
        $this->primaryColor = $primaryColor;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSecondaryColor(): ?string
    {
        return $this->secondaryColor;
    }

    public function setSecondaryColor(?string $secondaryColor): static
    {
        $this->secondaryColor = $secondaryColor;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSpecialInstructions(): ?string
    {
        return $this->specialInstructions;
    }

    public function setSpecialInstructions(?string $specialInstructions): static
    {
        $this->specialInstructions = $specialInstructions;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function acceptsReservations(): ?bool
    {
        return $this->acceptsReservations;
    }

    public function setAcceptsReservations(?bool $acceptsReservations): static
    {
        $this->acceptsReservations = $acceptsReservations;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function hasDelivery(): ?bool
    {
        return $this->hasDelivery;
    }

    public function setHasDelivery(?bool $hasDelivery): static
    {
        $this->hasDelivery = $hasDelivery;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function hasTakeout(): ?bool
    {
        return $this->hasTakeout;
    }

    public function setHasTakeout(?bool $hasTakeout): static
    {
        $this->hasTakeout = $hasTakeout;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getMinimumOrderAmount(): ?string
    {
        return $this->minimumOrderAmount;
    }

    public function setMinimumOrderAmount(?string $minimumOrderAmount): static
    {
        $this->minimumOrderAmount = $minimumOrderAmount;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDeliveryFee(): ?string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(?string $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getEstimatedDeliveryTime(): ?int
    {
        return $this->estimatedDeliveryTime;
    }

    public function setEstimatedDeliveryTime(?int $estimatedDeliveryTime): static
    {
        $this->estimatedDeliveryTime = $estimatedDeliveryTime;
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

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(?bool $isVerified): static
    {
        $this->isVerified = $isVerified;
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
     * @return Collection<int, BusinessHours>
     */
    public function getBusinessHours(): Collection
    {
        return $this->businessHours;
    }

    public function addBusinessHour(BusinessHours $businessHour): static
    {
        if (!$this->businessHours->contains($businessHour)) {
            $this->businessHours->add($businessHour);
            $businessHour->setRestaurant($this);
        }
        return $this;
    }

    public function removeBusinessHour(BusinessHours $businessHour): static
    {
        if ($this->businessHours->removeElement($businessHour)) {
            if ($businessHour->getRestaurant() === $this) {
                $businessHour->setRestaurant(null);
            }
        }
        return $this;
    }

    // Keep existing collection methods for tables, categories, menuItems, orders...
    /**
     * @return Collection<int, Table>
     */
    public function getTables(): Collection
    {
        return $this->tables;
    }

    public function addTable(Table $table): static
    {
        if (!$this->tables->contains($table)) {
            $this->tables->add($table);
            $table->setRestaurant($this);
        }
        return $this;
    }

    public function removeTable(Table $table): static
    {
        if ($this->tables->removeElement($table)) {
            if ($table->getRestaurant() === $this) {
                $table->setRestaurant(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setRestaurant($this);
        }
        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            if ($category->getRestaurant() === $this) {
                $category->setRestaurant(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getMenuItems(): Collection
    {
        return $this->menuItems;
    }

    public function addMenuItem(MenuItem $menuItem): static
    {
        if (!$this->menuItems->contains($menuItem)) {
            $this->menuItems->add($menuItem);
            $menuItem->setRestaurant($this);
        }
        return $this;
    }

    public function removeMenuItem(MenuItem $menuItem): static
    {
        if ($this->menuItems->removeElement($menuItem)) {
            if ($menuItem->getRestaurant() === $this) {
                $menuItem->setRestaurant(null);
            }
        }
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
            $order->setRestaurant($this);
        }
        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getRestaurant() === $this) {
                $order->setRestaurant(null);
            }
        }
        return $this;
    }

    // Business logic methods
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postalCode,
            $this->country
        ]);
        
        return implode(', ', $parts);
    }

    public function hasService(string $serviceType): bool
    {
        return in_array($serviceType, $this->serviceTypes ?? []);
    }

    public function isProfileComplete(): bool
    {
        return !empty($this->name) &&
               !empty($this->description) &&
               !empty($this->address) &&
               !empty($this->phoneNumber) &&
               !empty($this->cuisineType) &&
               !empty($this->serviceTypes);
    }

    public function getProfileCompletionPercentage(): int
    {
        $fields = [
            'name' => !empty($this->name),
            'description' => !empty($this->description),
            'address' => !empty($this->address),
            'phoneNumber' => !empty($this->phoneNumber),
            'email' => !empty($this->email),
            'cuisineType' => !empty($this->cuisineType),
            'serviceTypes' => !empty($this->serviceTypes),
            'logoUrl' => !empty($this->logoUrl),
            'businessHours' => $this->businessHours->count() > 0
        ];

        $completedFields = array_filter($fields);
        return (int) round((count($completedFields) / count($fields)) * 100);
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    public static function getCuisineChoices(): array
    {
        return [
            self::CUISINE_ITALIAN => 'Italian',
            self::CUISINE_CHINESE => 'Chinese',
            self::CUISINE_INDIAN => 'Indian',
            self::CUISINE_MEXICAN => 'Mexican',
            self::CUISINE_AMERICAN => 'American',
            self::CUISINE_FAST_FOOD => 'Fast Food',
            self::CUISINE_CAFE => 'Cafe',
            self::CUISINE_OTHER => 'Other'
        ];
    }

    public static function getServiceChoices(): array
    {
        return [
            self::SERVICE_DINE_IN => 'Dine In',
            self::SERVICE_TAKEOUT => 'Takeout',
            self::SERVICE_DELIVERY => 'Delivery'
        ];
    }
}
