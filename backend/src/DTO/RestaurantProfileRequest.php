<?php

namespace App\DTO;

use App\Entity\Restaurant;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for Restaurant Profile update requests
 * Validates restaurant profile data with comprehensive validation rules
 */
class RestaurantProfileRequest
{
    #[Assert\NotBlank(message: 'Restaurant name is required')]
    #[Assert\Length(max: 255, maxMessage: 'Restaurant name cannot be longer than {{ limit }} characters')]
    public string $name;

    #[Assert\Length(max: 1000, maxMessage: 'Description cannot be longer than {{ limit }} characters')]
    public ?string $description = null;

    #[Assert\Length(max: 20, maxMessage: 'Phone number cannot be longer than {{ limit }} characters')]
    public ?string $phoneNumber = null;

    #[Assert\Email(message: 'Please provide a valid email address')]
    #[Assert\Length(max: 180, maxMessage: 'Email cannot be longer than {{ limit }} characters')]
    public ?string $email = null;

    #[Assert\Length(max: 500, maxMessage: 'Address cannot be longer than {{ limit }} characters')]
    public ?string $address = null;

    #[Assert\Length(max: 100, maxMessage: 'City cannot be longer than {{ limit }} characters')]
    public ?string $city = null;

    #[Assert\Length(max: 100, maxMessage: 'State cannot be longer than {{ limit }} characters')]
    public ?string $state = null;

    #[Assert\Length(max: 20, maxMessage: 'Postal code cannot be longer than {{ limit }} characters')]
    public ?string $postalCode = null;

    #[Assert\Length(max: 100, maxMessage: 'Country cannot be longer than {{ limit }} characters')]
    public ?string $country = null;

    #[Assert\Choice(choices: [
        Restaurant::CUISINE_ITALIAN, Restaurant::CUISINE_CHINESE, Restaurant::CUISINE_INDIAN,
        Restaurant::CUISINE_MEXICAN, Restaurant::CUISINE_AMERICAN, Restaurant::CUISINE_FAST_FOOD,
        Restaurant::CUISINE_CAFE, Restaurant::CUISINE_OTHER
    ], message: 'Please select a valid cuisine type')]
    public ?string $cuisineType = null;

    #[Assert\All([
        new Assert\Choice(choices: [
            Restaurant::SERVICE_DINE_IN, Restaurant::SERVICE_TAKEOUT, Restaurant::SERVICE_DELIVERY
        ])
    ])]
    public ?array $serviceTypes = [];

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Primary color must be a valid hex color')]
    public ?string $primaryColor = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Secondary color must be a valid hex color')]
    public ?string $secondaryColor = null;

    #[Assert\Length(max: 1000, maxMessage: 'Special instructions cannot be longer than {{ limit }} characters')]
    public ?string $specialInstructions = null;

    public ?bool $acceptsReservations = null;

    public ?bool $hasDelivery = null;

    public ?bool $hasTakeout = null;

    #[Assert\Type(type: 'numeric', message: 'Minimum order amount must be a number')]
    #[Assert\PositiveOrZero(message: 'Minimum order amount must be positive')]
    public ?string $minimumOrderAmount = null;

    #[Assert\Type(type: 'numeric', message: 'Delivery fee must be a number')]
    #[Assert\PositiveOrZero(message: 'Delivery fee must be positive')]
    public ?string $deliveryFee = null;

    #[Assert\Type(type: 'integer', message: 'Estimated delivery time must be an integer')]
    #[Assert\Positive(message: 'Estimated delivery time must be positive')]
    public ?int $estimatedDeliveryTime = null;
}
