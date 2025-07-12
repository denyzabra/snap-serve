<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for Staff Invitation requests
 */
class StaffInvitationRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot be longer than {{ limit }} characters')]
    public string $email;

    #[Assert\NotBlank(message: 'First name is required')]
    #[Assert\Length(max: 100, maxMessage: 'First name cannot be longer than {{ limit }} characters')]
    public string $firstName;

    #[Assert\NotBlank(message: 'Last name is required')]
    #[Assert\Length(max: 100, maxMessage: 'Last name cannot be longer than {{ limit }} characters')]
    public string $lastName;

    #[Assert\NotBlank(message: 'Role is required')]
    #[Assert\Choice(choices: ['ROLE_STAFF', 'ROLE_MANAGER'], message: 'Please select a valid role')]
    public string $role;

    #[Assert\Length(max: 500, maxMessage: 'Message cannot be longer than {{ limit }} characters')]
    public ?string $message = null;

    #[Assert\Type(type: 'integer', message: 'Expiry days must be a number')]
    #[Assert\Range(min: 1, max: 30, notInRangeMessage: 'Expiry days must be between {{ min }} and {{ max }}')]
    public int $expiryDays = 7;
}
