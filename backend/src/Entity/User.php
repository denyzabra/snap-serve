<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'This email is already registered.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Role constants for SnapServe
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_CUSTOMER = 'ROLE_CUSTOMER';
    public const ROLE_STAFF = 'ROLE_STAFF';
    public const ROLE_MANAGER = 'ROLE_MANAGER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phoneNumber = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Assert\NotBlank]
    private ?string $password = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(name: 'email_verified')]
    private ?bool $emailVerified = false;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $createdAt = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastLoginAt = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'customer')]
    private Collection $orders;

    /**
     * @var Collection<int, VerificationToken>
     */
    #[ORM\OneToMany(targetEntity: VerificationToken::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $verificationTokens;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->verificationTokens = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isActive = true;
        $this->emailVerified = false;
        $this->roles = [self::ROLE_USER];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
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

    public function isEmailVerified(): ?bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;
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

    public function getLastLoginAt(): ?\DateTime
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTime $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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
            $order->setCustomer($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getCustomer() === $this) {
                $order->setCustomer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, VerificationToken>
     */
    public function getVerificationTokens(): Collection
    {
        return $this->verificationTokens;
    }

    public function addVerificationToken(VerificationToken $verificationToken): static
    {
        if (!$this->verificationTokens->contains($verificationToken)) {
            $this->verificationTokens->add($verificationToken);
            $verificationToken->setUser($this);
        }

        return $this;
    }

    public function removeVerificationToken(VerificationToken $verificationToken): static
    {
        if ($this->verificationTokens->removeElement($verificationToken)) {
            if ($verificationToken->getUser() === $this) {
                $verificationToken->setUser(null);
            }
        }

        return $this;
    }

    // Business logic methods
    public function getFullName(): string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);
        return implode(' ', $parts) ?: $this->email;
    }

    public function getInitials(): string
    {
        $initials = '';
        
        if ($this->firstName) {
            $initials .= strtoupper(substr($this->firstName, 0, 1));
        }
        
        if ($this->lastName) {
            $initials .= strtoupper(substr($this->lastName, 0, 1));
        }
        
        return $initials ?: strtoupper(substr($this->email, 0, 1));
    }

    public function isAdmin(): bool
    {
        return in_array(self::ROLE_ADMIN, $this->getRoles());
    }

    public function isManager(): bool
    {
        return in_array(self::ROLE_MANAGER, $this->getRoles()) || $this->isAdmin();
    }

    public function isStaff(): bool
    {
        return in_array(self::ROLE_STAFF, $this->getRoles()) || $this->isManager();
    }

    public function isCustomer(): bool
    {
        return in_array(self::ROLE_CUSTOMER, $this->getRoles());
    }

    public function addRole(string $role): static
    {
        if (!in_array($role, $this->roles)) {
            $this->roles[] = $role;
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function removeRole(string $role): static
    {
        if (($key = array_search($role, $this->roles)) !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function recordLogin(): static
    {
        $this->lastLoginAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getTotalOrders(): int
    {
        return $this->orders->count();
    }

    public function getTotalOrdersThisMonth(): int
    {
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');
        
        return $this->orders->filter(function (Order $order) use ($startOfMonth, $endOfMonth) {
            return $order->getCreatedAt() >= $startOfMonth && $order->getCreatedAt() <= $endOfMonth;
        })->count();
    }

    public function getRecentOrders(int $limit = 5): Collection
    {
        $orders = $this->orders->toArray();
        usort($orders, function (Order $a, Order $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });
        
        return new ArrayCollection(array_slice($orders, 0, $limit));
    }

    public static function getRoleChoices(): array
    {
        return [
            self::ROLE_USER => 'User',
            self::ROLE_CUSTOMER => 'Customer',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_ADMIN => 'Admin',
        ];
    }
}
