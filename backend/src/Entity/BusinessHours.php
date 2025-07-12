<?php

namespace App\Entity;

use App\Repository\BusinessHoursRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BusinessHoursRepository::class)]
#[ORM\Table(name: 'business_hours')]
class BusinessHours
{
    public const DAY_MONDAY = 'monday';
    public const DAY_TUESDAY = 'tuesday';
    public const DAY_WEDNESDAY = 'wednesday';
    public const DAY_THURSDAY = 'thursday';
    public const DAY_FRIDAY = 'friday';
    public const DAY_SATURDAY = 'saturday';
    public const DAY_SUNDAY = 'sunday';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'businessHours')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Restaurant $restaurant = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::DAY_MONDAY, self::DAY_TUESDAY, self::DAY_WEDNESDAY,
        self::DAY_THURSDAY, self::DAY_FRIDAY, self::DAY_SATURDAY, self::DAY_SUNDAY
    ])]
    private ?string $dayOfWeek = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $openTime = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $closeTime = null;

    #[ORM\Column]
    private ?bool $isOpen = true;

    #[ORM\Column(nullable: true)]
    private ?bool $is24Hours = false;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isOpen = true;
        $this->is24Hours = false;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDayOfWeek(): ?string
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(string $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getOpenTime(): ?\DateTimeInterface
    {
        return $this->openTime;
    }

    public function setOpenTime(?\DateTimeInterface $openTime): static
    {
        $this->openTime = $openTime;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCloseTime(): ?\DateTimeInterface
    {
        return $this->closeTime;
    }

    public function setCloseTime(?\DateTimeInterface $closeTime): static
    {
        $this->closeTime = $closeTime;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isOpen(): ?bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function is24Hours(): ?bool
    {
        return $this->is24Hours;
    }

    public function setIs24Hours(?bool $is24Hours): static
    {
        $this->is24Hours = $is24Hours;
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

    // Business logic methods
    public function getFormattedHours(): string
    {
        if (!$this->isOpen) {
            return 'Closed';
        }

        if ($this->is24Hours) {
            return '24 Hours';
        }

        if ($this->openTime && $this->closeTime) {
            return sprintf('%s - %s', 
                $this->openTime->format('g:i A'),
                $this->closeTime->format('g:i A')
            );
        }

        return 'Hours not set';
    }

    public function isCurrentlyOpen(): bool
    {
        if (!$this->isOpen) {
            return false;
        }

        if ($this->is24Hours) {
            return true;
        }

        if (!$this->openTime || !$this->closeTime) {
            return false;
        }

        $now = new \DateTime();
        $currentTime = $now->format('H:i');
        $openTime = $this->openTime->format('H:i');
        $closeTime = $this->closeTime->format('H:i');

        // Handle overnight hours (e.g., 10 PM to 2 AM)
        if ($closeTime < $openTime) {
            return $currentTime >= $openTime || $currentTime <= $closeTime;
        }

        return $currentTime >= $openTime && $currentTime <= $closeTime;
    }

    public static function getDayChoices(): array
    {
        return [
            self::DAY_MONDAY => 'Monday',
            self::DAY_TUESDAY => 'Tuesday',
            self::DAY_WEDNESDAY => 'Wednesday',
            self::DAY_THURSDAY => 'Thursday',
            self::DAY_FRIDAY => 'Friday',
            self::DAY_SATURDAY => 'Saturday',
            self::DAY_SUNDAY => 'Sunday'
        ];
    }
}
