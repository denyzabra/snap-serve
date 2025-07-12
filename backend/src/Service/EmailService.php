<?php

namespace App\Service;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Entity\StaffInvitation;
use App\Entity\VerificationToken;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Service for sending various types of emails in the SnapServe application
 * Handles admin verification, welcome emails, password resets, and order confirmations
 */
class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private Environment $twig,
        #[Autowire('%env(MAILER_FROM_EMAIL)%')] 
        private string $fromEmail = 'dev@snapserve.local',
        #[Autowire('%env(MAILER_FROM_NAME)%')] 
        private string $fromName = 'SnapServe Development',
        #[Autowire('%env(APP_URL)%')] 
        private string $appUrl = 'http://localhost:7070'
    ) {
    }

    /**
     * Send admin verification email after registration
     * 
     * @param User $user The admin user to verify
     * @param Restaurant $restaurant The restaurant being registered
     * @param string $verificationUrl The verification URL
     * @return bool True if email was sent successfully
     */
    public function sendAdminVerificationEmail(User $user, Restaurant $restaurant, string $verificationUrl): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Verify Your SnapServe Admin Account')
                ->htmlTemplate('emails/admin_verification.html.twig')
                ->context([
                    'user' => $user,
                    'restaurant' => $restaurant,
                    'verificationUrl' => $verificationUrl,
                    'appUrl' => $this->appUrl,
                    'supportEmail' => $this->fromEmail
                ]);

            $this->mailer->send($email);

            $this->logger->info('Admin verification email sent successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'restaurant_id' => $restaurant->getId(),
                'restaurant_name' => $restaurant->getName(),
                'verification_url_sent' => true
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send admin verification email', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'restaurant_id' => $restaurant->getId(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error sending admin verification email', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);

            return false;
        }
    }

    /**
     * Send welcome email after successful verification
     * 
     * @param User $user The verified admin user
     * @param Restaurant $restaurant The activated restaurant
     * @return bool True if email was sent successfully
     */
    public function sendWelcomeEmail(User $user, Restaurant $restaurant): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Welcome to SnapServe! Your Restaurant is Now Active')
                ->htmlTemplate('emails/welcome.html.twig')
                ->context([
                    'user' => $user,
                    'restaurant' => $restaurant,
                    'appUrl' => $this->appUrl,
                    'dashboardUrl' => $this->appUrl . '/admin/dashboard',
                    'supportEmail' => $this->fromEmail
                ]);

            $this->mailer->send($email);

            $this->logger->info('Welcome email sent successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'restaurant_id' => $restaurant->getId(),
                'restaurant_name' => $restaurant->getName()
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send welcome email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send password reset email
     * 
     * @param User $user The user requesting password reset
     * @param string $resetUrl The password reset URL
     * @return bool True if email was sent successfully
     */
    public function sendPasswordResetEmail(User $user, string $resetUrl): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Reset Your SnapServe Password')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'appUrl' => $this->appUrl,
                    'supportEmail' => $this->fromEmail,
                    'expirationHours' => 24
                ]);

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send order confirmation email to customer
     * 
     * @param User $customer The customer who placed the order
     * @param array $orderData Order details
     * @return bool True if email was sent successfully
     */
    public function sendOrderConfirmationEmail(User $customer, array $orderData): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($customer->getEmail(), $customer->getFullName()))
                ->subject('Order Confirmation - SnapServe')
                ->htmlTemplate('emails/order_confirmation.html.twig')
                ->context([
                    'customer' => $customer,
                    'order' => $orderData,
                    'appUrl' => $this->appUrl,
                    'supportEmail' => $this->fromEmail
                ]);

            $this->mailer->send($email);

            $this->logger->info('Order confirmation email sent successfully', [
                'customer_id' => $customer->getId(),
                'order_id' => $orderData['id'] ?? 'unknown',
                'order_total' => $orderData['total'] ?? 'unknown'
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send order confirmation email', [
                'customer_id' => $customer->getId(),
                'order_id' => $orderData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send notification email to restaurant staff
     * 
     * @param User $staff The staff member to notify
     * @param string $subject Email subject
     * @param string $message Email message
     * @param array $context Additional context data
     * @return bool True if email was sent successfully
     */
    public function sendStaffNotificationEmail(User $staff, string $subject, string $message, array $context = []): bool
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($staff->getEmail(), $staff->getFullName()))
                ->subject($subject)
                ->htmlTemplate('emails/staff_notification.html.twig')
                ->context(array_merge([
                    'staff' => $staff,
                    'message' => $message,
                    'appUrl' => $this->appUrl,
                    'supportEmail' => $this->fromEmail
                ], $context));

            $this->mailer->send($email);

            $this->logger->info('Staff notification email sent successfully', [
                'staff_id' => $staff->getId(),
                'subject' => $subject
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send staff notification email', [
                'staff_id' => $staff->getId(),
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send bulk email to multiple recipients
     * 
     * @param array $recipients Array of email addresses or Address objects
     * @param string $subject Email subject
     * @param string $template Template name
     * @param array $context Template context
     * @return int Number of successfully sent emails
     */
    public function sendBulkEmail(array $recipients, string $subject, string $template, array $context = []): int
    {
        $sentCount = 0;
        $totalRecipients = count($recipients);

        $this->logger->info('Starting bulk email campaign', [
            'total_recipients' => $totalRecipients,
            'template' => $template,
            'subject' => $subject
        ]);

        foreach ($recipients as $recipient) {
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to($recipient instanceof Address ? $recipient : new Address($recipient))
                    ->subject($subject)
                    ->htmlTemplate($template)
                    ->context(array_merge([
                        'appUrl' => $this->appUrl,
                        'supportEmail' => $this->fromEmail
                    ], $context));

                $this->mailer->send($email);
                $sentCount++;

                // Small delay to prevent overwhelming the mail server
                usleep(100000); // 0.1 second delay

            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Failed to send bulk email to recipient', [
                    'recipient' => $recipient instanceof Address ? $recipient->getAddress() : $recipient,
                    'template' => $template,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Bulk email campaign completed', [
            'total_recipients' => $totalRecipients,
            'successfully_sent' => $sentCount,
            'failed' => $totalRecipients - $sentCount,
            'success_rate' => round(($sentCount / $totalRecipients) * 100, 2) . '%',
            'template' => $template
        ]);

        return $sentCount;
    }

    /**
     * Send test email to verify email configuration
     * 
     * @param string $testEmail Email address to send test to
     * @return bool True if test email was sent successfully
     */
    public function sendTestEmail(string $testEmail): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($testEmail))
                ->subject('SnapServe Email Configuration Test')
                ->html($this->generateTestEmailHtml());

            $this->mailer->send($email);

            $this->logger->info('Test email sent successfully', [
                'test_email' => $testEmail,
                'from_email' => $this->fromEmail,
                'app_url' => $this->appUrl
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send test email', [
                'test_email' => $testEmail,
                'error' => $e->getMessage(),
                'mailer_dsn' => 'configured'
            ]);

            return false;
        }
    }

    /**
     * Generate HTML content for test email
     * 
     * @return string HTML content
     */
    private function generateTestEmailHtml(): string
    {
        $currentTime = new \DateTime();
        $environment = $_ENV['APP_ENV'] ?? 'development';
        
        return "
        <html>
        <head>
            <title>SnapServe Email Test</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
                .content { padding: 20px 0; }
                .footer { background-color: #e9ecef; padding: 15px; text-align: center; border-radius: 5px; font-size: 12px; }
                .success { color: #28a745; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 SnapServe Email Test</h1>
                    <p class='success'>Email configuration is working correctly!</p>
                </div>
                
                <div class='content'>
                    <h2>Configuration Details:</h2>
                    <ul>
                        <li><strong>Environment:</strong> {$environment}</li>
                        <li><strong>From Email:</strong> {$this->fromEmail}</li>
                        <li><strong>From Name:</strong> {$this->fromName}</li>
                        <li><strong>App URL:</strong> {$this->appUrl}</li>
                        <li><strong>Sent At:</strong> {$currentTime->format('Y-m-d H:i:s')}</li>
                    </ul>
                    
                    <p>This test email confirms that your SnapServe email service is properly configured and can send emails successfully.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated test email from SnapServe Email Service</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Get email configuration status
     * 
     * @return array Configuration status information
     */
    public function getEmailConfigStatus(): array
    {
        return [
            'service_name' => 'SnapServe EmailService',
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
            'app_url' => $this->appUrl,
            'mailer_configured' => !empty($this->fromEmail),
            'environment' => $_ENV['APP_ENV'] ?? 'development',
            'twig_integration' => $this->twig instanceof Environment,
            'logger_integration' => $this->logger instanceof LoggerInterface,
            'timestamp' => new \DateTime(),
            'version' => '1.0.0'
        ];
    }

    /**
     * Validate email address format
     * 
     * @param string $email Email address to validate
     * @return bool True if email is valid
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Create a standard email address object
     * 
     * @param string $email Email address
     * @param string|null $name Optional name
     * @return Address Email address object
     */
    public function createAddress(string $email, ?string $name = null): Address
    {
        if (!$this->validateEmail($email)) {
            throw new \InvalidArgumentException("Invalid email address: {$email}");
        }

        return new Address($email, $name);
    }

    /**
     * Check if email service is healthy
     * 
     * @return bool True if service is healthy
     */
    public function isHealthy(): bool
    {
        try {
            // Check if all required dependencies are available
            $checks = [
                'mailer' => $this->mailer instanceof MailerInterface,
                'logger' => $this->logger instanceof LoggerInterface,
                'twig' => $this->twig instanceof Environment,
                'from_email' => !empty($this->fromEmail) && $this->validateEmail($this->fromEmail),
                'app_url' => !empty($this->appUrl)
            ];

            return !in_array(false, $checks, true);

        } catch (\Exception $e) {
            $this->logger->error('EmailService health check failed', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
 * Send user verification email (for non-admin users)
 * 
 * @param User $user The user to verify
 * @param string $verificationUrl The verification URL
 * @return bool True if email was sent successfully
 */
public function sendUserVerificationEmail(User $user, string $verificationUrl): bool
{
    try {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Verify Your SnapServe Account')
            ->htmlTemplate('emails/user_verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'appUrl' => $this->appUrl,
                'supportEmail' => $this->fromEmail
            ]);

        $this->mailer->send($email);

        $this->logger->info('User verification email sent successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'user_type' => 'regular_user'
        ]);

        return true;

    } catch (TransportExceptionInterface $e) {
        $this->logger->error('Failed to send user verification email', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return false;
    }
}

/**
 * Send staff invitation email
 */
public function sendStaffInvitationEmail(StaffInvitation $invitation, string $invitationUrl, ?string $customMessage = null): bool
{
    try {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($invitation->getEmail(), $invitation->getFullName()))
            ->subject('Staff Invitation - ' . $invitation->getRestaurant()->getName())
            ->htmlTemplate('emails/staff_invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'restaurant' => $invitation->getRestaurant(),
                'invitedBy' => $invitation->getInvitedBy(),
                'invitationUrl' => $invitationUrl,
                'customMessage' => $customMessage,
                'appUrl' => $this->appUrl,
                'supportEmail' => $this->fromEmail
            ]);

        $this->mailer->send($email);

        $this->logger->info('Staff invitation email sent successfully', [
            'invitation_id' => $invitation->getId(),
            'email' => $invitation->getEmail(),
            'restaurant_id' => $invitation->getRestaurant()->getId()
        ]);

        return true;

    } catch (TransportExceptionInterface $e) {
        $this->logger->error('Failed to send staff invitation email', [
            'invitation_id' => $invitation->getId(),
            'email' => $invitation->getEmail(),
            'error' => $e->getMessage()
        ]);

        return false;
    }
}


/**
 * Send staff welcome email after accepting invitation
 * 
 * @param User $user The new staff user
 * @param Restaurant $restaurant The restaurant
 * @return bool True if email was sent successfully
 */
public function sendStaffWelcomeEmail(User $user, Restaurant $restaurant): bool
{
    try {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Welcome to ' . $restaurant->getName() . ' Team!')
            ->htmlTemplate('emails/staff_welcome.html.twig')
            ->context([
                'user' => $user,
                'restaurant' => $restaurant,
                'appUrl' => $this->appUrl,
                'dashboardUrl' => $this->appUrl . '/staff/dashboard',
                'supportEmail' => $this->fromEmail
            ]);

        $this->mailer->send($email);

        $this->logger->info('Staff welcome email sent successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'restaurant_id' => $restaurant->getId()
        ]);

        return true;

    } catch (TransportExceptionInterface $e) {
        $this->logger->error('Failed to send staff welcome email', [
            'user_id' => $user->getId(),
            'error' => $e->getMessage()
        ]);

        return false;
    }
}

/**
 * Send staff role update notification email
 * 
 * @param User $user The staff user
 * @param string $oldRole The previous role
 * @param string $newRole The new role
 * @param Restaurant $restaurant The restaurant
 * @return bool True if email was sent successfully
 */
public function sendStaffRoleUpdateEmail(User $user, string $oldRole, string $newRole, Restaurant $restaurant): bool
{
    try {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Your Role Has Been Updated at ' . $restaurant->getName())
            ->htmlTemplate('emails/staff_role_update.html.twig')
            ->context([
                'user' => $user,
                'restaurant' => $restaurant,
                'oldRole' => $this->formatRole($oldRole),
                'newRole' => $this->formatRole($newRole),
                'appUrl' => $this->appUrl,
                'supportEmail' => $this->fromEmail
            ]);

        $this->mailer->send($email);

        $this->logger->info('Staff role update email sent successfully', [
            'user_id' => $user->getId(),
            'old_role' => $oldRole,
            'new_role' => $newRole
        ]);

        return true;

    } catch (TransportExceptionInterface $e) {
        $this->logger->error('Failed to send staff role update email', [
            'user_id' => $user->getId(),
            'error' => $e->getMessage()
        ]);

        return false;
    }
}

/**
 * Format role for display
 */
private function formatRole(string $role): string
{
    return match($role) {
        'ROLE_STAFF' => 'Staff Member',
        'ROLE_MANAGER' => 'Manager',
        'ROLE_ADMIN' => 'Administrator',
        default => ucfirst(str_replace('ROLE_', '', $role))
    };
}


}
