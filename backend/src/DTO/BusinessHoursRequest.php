<?php

namespace App\DTO;

use App\Entity\BusinessHours;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for Business Hours update requests
 * Handles restaurant operating hours validation
 */
class BusinessHoursRequest
{
    #[Assert\NotBlank(message: 'Day of week is required')]
    #[Assert\Choice(choices: [
        BusinessHours::DAY_MONDAY, BusinessHours::DAY_TUESDAY, BusinessHours::DAY_WEDNESDAY,
        BusinessHours::DAY_THURSDAY, BusinessHours::DAY_FRIDAY, BusinessHours::DAY_SATURDAY, 
        BusinessHours::DAY_SUNDAY
    ], message: 'Please select a valid day of the week')]
    public string $dayOfWeek;

    public ?bool $isOpen = true;

    public ?bool $is24Hours = false;

    #[Assert\Time(message: 'Please provide a valid open time')]
    public ?string $openTime = null;

    #[Assert\Time(message: 'Please provide a valid close time')]
    public ?string $closeTime = null;
}
