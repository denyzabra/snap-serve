<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for Staff Onboarding requests
 */
class StaffOnboardingRequest
{
    #[Assert\NotBlank(message: 'Token is required')]
    public string $token;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters long')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'
    )]
    public string $password;

    #[Assert\NotBlank(message: 'Password confirmation is required')]
    public string $passwordConfirmation;

    #[Assert\Length(max: 20, maxMessage: 'Phone number cannot be longer than {{ limit }} characters')]
    public ?string $phoneNumber = null;

    /**
     * Custom validation to check if passwords match
     */
    #[Assert\IsTrue(message: 'Passwords do not match')]
    public function isPasswordMatching(): bool
    {
        return $this->password === $this->passwordConfirmation;
    }
}
