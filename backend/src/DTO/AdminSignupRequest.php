<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for Admin Signup requests
 * This class defines the structure and validation rules for the admin signup API endpoint
 */
class AdminSignupRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot be longer than {{ limit }} characters')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters long')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'
    )]
    public string $password;

    #[Assert\NotBlank(message: 'First name is required')]
    #[Assert\Length(max: 100, maxMessage: 'First name cannot be longer than {{ limit }} characters')]
    public string $firstName;

    #[Assert\NotBlank(message: 'Last name is required')]
    #[Assert\Length(max: 100, maxMessage: 'Last name cannot be longer than {{ limit }} characters')]
    public string $lastName;

    #[Assert\NotBlank(message: 'Restaurant name is required')]
    #[Assert\Length(max: 255, maxMessage: 'Restaurant name cannot be longer than {{ limit }} characters')]
    public string $restaurantName;

    // Restaurant email is optional
    #[Assert\Email(message: 'Please provide a valid restaurant email address')]
    #[Assert\Length(max: 180, maxMessage: 'Restaurant email cannot be longer than {{ limit }} characters')]
    public ?string $restaurantEmail = null;
}
