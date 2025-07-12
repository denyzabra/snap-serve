<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for Admin Login requests
 * Validates login credentials with security constraints
 */
class LoginRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot be longer than {{ limit }} characters')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 1, minMessage: 'Password cannot be empty')]
    public string $password;

    /**
     * Optional: Remember me functionality for extended sessions
     */
    public bool $rememberMe = false;

    /**
     * Optional: Device identifier for security tracking
     */
    public ?string $deviceId = null;

    /**
     * Optional: User agent for security logging
     */
    public ?string $userAgent = null;
}
