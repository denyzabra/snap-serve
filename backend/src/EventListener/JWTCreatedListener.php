<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * JWT token creation event listener
 * Adds custom claims to JWT payload for enhanced security and context
 */
class JWTCreatedListener
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    /**
     * Add custom data to JWT payload
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $request = $this->requestStack->getCurrentRequest();

        // Add custom user information to JWT payload
        $payload['userId'] = $user->getId();
        $payload['email'] = $user->getEmail();
        $payload['roles'] = $user->getRoles();
        $payload['fullName'] = $user->getFullName();
        $payload['isActive'] = $user->isActive();

        // Add request information for security tracking
        if ($request) {
            $payload['ip'] = $request->getClientIp();
            $payload['userAgent'] = $request->headers->get('User-Agent');
        }

        // Add token metadata
        $payload['tokenType'] = 'access_token';
        $payload['issuedAt'] = time();
        $payload['issuer'] = 'SnapServe';
        
        // Add user type for frontend routing
        if ($user->isAdmin()) {
            $payload['userType'] = 'admin';
            $payload['dashboardUrl'] = '/admin/dashboard';
        } elseif ($user->isManager()) {
            $payload['userType'] = 'manager';
            $payload['dashboardUrl'] = '/manager/dashboard';
        } elseif ($user->isStaff()) {
            $payload['userType'] = 'staff';
            $payload['dashboardUrl'] = '/staff/dashboard';
        } else {
            $payload['userType'] = 'customer';
            $payload['dashboardUrl'] = '/customer/orders';
        }
        
        $event->setData($payload);
    }
}
