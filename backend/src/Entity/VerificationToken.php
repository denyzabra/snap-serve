<?php

namespace App\Entity;

use App\Repository\VerificationTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VerificationTokenRepository::class)]
#[ORM\Table(name: 'verification_token')]
class VerificationToken
{
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';
    public const TYPE_PASSWORD_RESET = 'password_reset';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private ?string $token = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::TYPE_EMAIL_VERIFICATION, self::TYPE_PASSWORD_RESET])]
    private ?string $type = null;

    #[ORM\ManyToOne(inversedBy: 'verificationTokens')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column]
    private ?bool $isUsed = false;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $usedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->isUsed = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): ?bool
    {
        return $this->isUsed;
    }

    public function setIsUsed(bool $isUsed): static
    {
        $this->isUsed = $isUsed;
        if ($isUsed && !$this->usedAt) {
            $this->usedAt = new \DateTime();
        }
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

    public function getUsedAt(): ?\DateTime
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTime $usedAt): static
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    // Business logic methods
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTime();
    }

    public function isValid(): bool
    {
        return !$this->isUsed && !$this->isExpired();
    }

    public function markAsUsed(): static
    {
        $this->isUsed = true;
        $this->usedAt = new \DateTime();
        return $this;
    }
}
