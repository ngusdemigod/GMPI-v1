<?php
/**
 * Resend Email Service
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Service for sending transaction receipts and notifications via Resend API
 */

require_once __DIR__ . '/resend_config.php';

class ResendService {
    private $apiKey;
    private $fromEmail;
    private $fromName;
    private $apiUrl;
    
    public function __construct() {
        $this->apiKey = RESEND_API_KEY;
        $this->fromEmail = RESEND_FROM_EMAIL;
        $this->fromName = RESEND_FROM_NAME;
        $this->apiUrl = RESEND_API_URL;

        error_log(sprintf(
            '[ResendService] initialized fromEmail=%s apiUrl=%s key=%s',
            $this->fromEmail,
            $this->apiUrl,
            $this->maskSecret($this->apiKey)
        ));
    }
    
    /**
     * Send donation receipt email
     * 
     * @param array $data Email data
     * @return array Response
     */
    public function sendDonationReceipt($data) {
        try {
            $toEmail = $data['email'];
            $firstName = $data['first_name'] ?? 'Partner';
            $amount = $data['amount'];
            $currency = $data['currency'] ?? 'USD';
            $category = $data['category'] ?? 'Donation';
            $reference = $data['reference'];
            $date = $data['date'] ?? date('Y-m-d');
            $project = $data['project'] ?? null;
            
            $subject = 'Thank you for your generous gift!';
            
            $html = $this->getDonationReceiptHtml($firstName, $amount, $currency, $category, $reference, $date, $project);
            
            return $this->sendEmail([
                'to' => [$toEmail],
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'subject' => $subject,
                'html' => $html
            ]);
            
        } catch (Exception $e) {
            error_log("Resend sendDonationReceipt error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send receipt email'];
        }
    }
    
    /**
     * Send subscription confirmation email
     * 
     * @param array $data Email data
     * @return array Response
     */
    public function sendSubscriptionConfirmation($data) {
        try {
            $toEmail = $data['email'];
            $firstName = $data['first_name'] ?? 'Partner';
            $amount = $data['amount'];
            $currency = $data['currency'] ?? 'USD';
            $frequency = $data['frequency'] ?? 'monthly';
            $nextBillingDate = $data['next_billing_date'] ?? null;
            $subscriptionId = $data['subscription_id'] ?? null;
            
            $subject = 'Your recurring donation is active!';
            
            $html = $this->getSubscriptionConfirmationHtml($firstName, $amount, $currency, $frequency, $nextBillingDate, $subscriptionId);
            
            return $this->sendEmail([
                'to' => [$toEmail],
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'subject' => $subject,
                'html' => $html
            ]);
            
        } catch (Exception $e) {
            error_log("Resend sendSubscriptionConfirmation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send subscription confirmation'];
        }
    }
    
    /**
     * Send payment failed email
     * 
     * @param array $data Email data
     * @return array Response
     */
    public function sendPaymentFailed($data) {
        try {
            $toEmail = $data['email'];
            $firstName = $data['first_name'] ?? 'Partner';
            $amount = $data['amount'];
            $currency = $data['currency'] ?? 'USD';
            $reference = $data['reference'] ?? null;
            
            $subject = 'Payment issue - Action required';
            
            $html = $this->getPaymentFailedHtml($firstName, $amount, $currency, $reference);
            
            return $this->sendEmail([
                'to' => [$toEmail],
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'subject' => $subject,
                'html' => $html
            ]);
            
        } catch (Exception $e) {
            error_log("Resend sendPaymentFailed error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send payment failed email'];
        }
    }
    
    /**
     * Send email verification email
     * 
     * @param array $data Email data
     * @return array Response
     */
    public function sendVerificationEmail($data) {
        try {
            $toEmail = $data['email'];
            $firstName = $data['first_name'] ?? 'Partner';
            $verificationCode = $data['verification_code'];
            
            $subject = 'Verify your email address - Bright Light Ministry Int\'l';
            
            $html = $this->getVerificationEmailHtml($firstName, $verificationCode);
            
            return $this->sendEmail([
                'to' => [$toEmail],
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'subject' => $subject,
                'html' => $html
            ]);
            
        } catch (Exception $e) {
            error_log("Resend sendVerificationEmail error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send verification email'];
        }
    }

    /**
     * Send admin notification for a newly submitted support ticket.
     *
     * @param array $data
     * @return array
     */
    public function sendSupportTicketNotification($data) {
        try {
            $toEmail = $data['email'];
            $ticketId = $data['ticket_id'] ?? '';
            $subjectLine = $data['subject_line'] ?? 'Support request';
            $category = $data['category'] ?? 'other';
            $submittedBy = trim((string) ($data['submitted_by'] ?? 'Authenticated user'));
            $submitterEmail = $data['submitter_email'] ?? '';
            $message = $data['message'] ?? '';
            $adminUrl = $data['admin_url'] ?? '';

            $subject = sprintf('New Support Ticket #%s: %s', $ticketId, $subjectLine);
            $html = $this->getSupportTicketNotificationHtml(
                $ticketId,
                $subjectLine,
                $category,
                $submittedBy,
                $submitterEmail,
                $message,
                $adminUrl
            );

            return $this->sendEmail([
                'to' => [$toEmail],
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'subject' => $subject,
                'html' => $html
            ]);
        } catch (Exception $e) {
            error_log("Resend sendSupportTicketNotification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send support notification email'];
        }
    }
    
    /**
     * Send subscription cancelled email
     * 
     * @param array $data Email data
     * @return array Response
     */
    public function sendSubscriptionCancelled($data) {
        try {
            $toEmail = $data['email'];
            $firstName = $data['first_name'] ?? 'Partner';
            $subscriptionId = $data['subscription_id'] ?? null;
            
            $subject = 'Subscription cancelled';
            
            $html = $this->getSubscriptionCancelledHtml($firstName, $subscriptionId);
            
            return $this->sendEmail([
                'to' => [$toEmail],
                'from' => "{$this->fromName} <{$this->fromEmail}>",
                'subject' => $subject,
                'html' => $html
            ]);
            
        } catch (Exception $e) {
            error_log("Resend sendSubscriptionCancelled error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send subscription cancelled email'];
        }
    }
    
    /**
     * Send generic email
     * 
     * @param array $data Email data
     * @return array Response
     */
    public function sendEmail($data) {
        try {
            if (empty($this->apiKey)) {
                throw new Exception('Resend is not configured. Set RESEND_API_KEY.');
            }

            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/emails');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: {$error}");
            }
            
            $decoded = json_decode($response, true);
            
            if ($httpCode >= 400) {
                throw new Exception("Resend API error: " . ($decoded['message'] ?? 'Unknown error'));
            }

            error_log(sprintf(
                '[ResendService] Resend success to=%s http=%s body=%s',
                implode(',', $data['to'] ?? []),
                $httpCode,
                $response
            ));
            
            return [
                'success' => true,
                'id' => $decoded['id'] ?? null,
                'response' => $decoded
            ];
            
        } catch (Exception $e) {
            error_log("Resend sendEmail error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function maskSecret($secret) {
        $secret = (string) $secret;
        $length = strlen($secret);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($secret, -4);
    }
    
    /**
     * Get donation receipt HTML template
     */
    private function getDonationReceiptHtml($firstName, $amount, $currency, $category, $reference, $date, $project) {
        $formattedAmount = number_format($amount, 2);
        $projectHtml = $project ? "<tr><td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Project:</strong></td><td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>".htmlspecialchars($project)."</span></td></tr>" : '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Thank you for your generous gift!</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f5;'>
            <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 40px 20px;'>
                        <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #122137 0%, #0F1B2D 100%); padding: 40px 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #C9A24B; font-size: 28px; font-family: Georgia, serif;'>Bright Light Ministry Int'l</h1>
                                    <p style='margin: 10px 0 0 0; color: #E7D9B4; font-size: 14px;'>Partnership Portal</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <h2 style='margin: 0 0 20px 0; color: #0F1B2D; font-size: 24px;'>Thank you, {$firstName}!</h2>
                                    <p style='margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.6;'>
                                        We are grateful for your generous gift. Your contribution helps us continue our ministry and serve our community.
                                    </p>
                                    <table role='presentation' style='width: 100%; border: 1px solid #E7D9B4; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                                        <tr>
                                            <td style='text-align: center; padding-bottom: 20px;'>
                                                <p style='margin: 0 0 5px 0; color: #666666; font-size: 14px;'>Total Contribution</p>
                                                <p style='margin: 0; color: #C9A24B; font-size: 36px; font-family: Georgia, serif;'>{$formattedAmount} {$currency}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='border-top: 1px solid #E7D9B4; padding-top: 20px;'>
                                                <table role='presentation' style='width: 100%;'>
                                                    <tr>
                                                        <td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Receipt Number:</strong></td>
                                                        <td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>{$reference}</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Date:</strong></td>
                                                        <td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>{$date}</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Category:</strong></td>
                                                        <td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>{$category}</span></td>
                                                    </tr>
                                                    {$projectHtml}
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style='margin: 20px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;'>
                                        This receipt is for your records. Contributions may be tax-deductible. Please consult your tax advisor for more information.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background-color: #f5f5f5; padding: 20px 30px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; color: #666666; font-size: 12px;'>
                                        Thank you for partnering with us in ministry!
                                    </p>
                                    <p style='margin: 0; color: #999999; font-size: 11px;'>
                                        Bright Light Ministry Int'l | support@brightlightministry.org
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
    
    /**
     * Get subscription confirmation HTML template
     */
    private function getSubscriptionConfirmationHtml($firstName, $amount, $currency, $frequency, $nextBillingDate, $subscriptionId) {
        $formattedAmount = number_format($amount, 2);
        $frequencyDisplay = ucfirst($frequency);
        $subscriptionIdHtml = $subscriptionId ? "<tr><td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Subscription ID:</strong></td><td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>".htmlspecialchars($subscriptionId)."</span></td></tr>" : '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Your recurring donation is active!</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f5;'>
            <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 40px 20px;'>
                        <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #5B7B6A 0%, #4a6557 100%); padding: 40px 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-family: Georgia, serif;'>Bright Light Ministry Int'l</h1>
                                    <p style='margin: 10px 0 0 0; color: #E7D9B4; font-size: 14px;'>Partnership Portal</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <h2 style='margin: 0 0 20px 0; color: #0F1B2D; font-size: 24px;'>Your recurring donation is active!</h2>
                                    <p style='margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.6;'>
                                        Thank you, {$firstName}! Your recurring donation has been set up successfully.
                                    </p>
                                    <table role='presentation' style='width: 100%; border: 1px solid #5B7B6A; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                                        <tr>
                                            <td style='text-align: center; padding-bottom: 20px;'>
                                                <p style='margin: 0 0 5px 0; color: #666666; font-size: 14px;'>Monthly Contribution</p>
                                                <p style='margin: 0; color: #5B7B6A; font-size: 36px; font-family: Georgia, serif;'>{$formattedAmount} {$currency}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='border-top: 1px solid #5B7B6A; padding-top: 20px;'>
                                                <table role='presentation' style='width: 100%;'>
                                                    <tr>
                                                        <td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Frequency:</strong></td>
                                                        <td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>{$frequencyDisplay}</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Next Billing Date:</strong></td>
                                                        <td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>{$nextBillingDate}</span></td>
                                                    </tr>
                                                    {$subscriptionIdHtml}
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style='margin: 20px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;'>
                                        You will receive a receipt for each payment. To manage or cancel your subscription, please contact our support team.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background-color: #f5f5f5; padding: 20px 30px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; color: #666666; font-size: 12px;'>
                                        Thank you for your faithful giving!
                                    </p>
                                    <p style='margin: 0; color: #999999; font-size: 11px;'>
                                        Bright Light Ministry Int'l | support@brightlightministry.org
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
    
    /**
     * Get payment failed HTML template
     */
    private function getPaymentFailedHtml($firstName, $amount, $currency, $reference) {
        $formattedAmount = number_format($amount, 2);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Payment issue - Action required</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f5;'>
            <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 40px 20px;'>
                        <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 40px 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-family: Georgia, serif;'>Bright Light Ministry Int'l</h1>
                                    <p style='margin: 10px 0 0 0; color: #E7D9B4; font-size: 14px;'>Partnership Portal</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <h2 style='margin: 0 0 20px 0; color: #dc3545; font-size: 24px;'>Payment Issue</h2>
                                    <p style='margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.6;'>
                                        Hello {$firstName},<br><br>
                                        We were unable to process your payment. Please update your payment information to complete your donation.
                                    </p>
                                    <table role='presentation' style='width: 100%; border: 1px solid #dc3545; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                                        <tr>
                                            <td style='text-align: center; padding-bottom: 20px;'>
                                                <p style='margin: 0 0 5px 0; color: #666666; font-size: 14px;'>Amount</p>
                                                <p style='margin: 0; color: #dc3545; font-size: 36px; font-family: Georgia, serif;'>{$formattedAmount} {$currency}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='border-top: 1px solid #dc3545; padding-top: 20px;'>
                                                <table role='presentation' style='width: 100%;'>
                                                    <tr>
                                                        <td style='padding: 5px 0;'><strong style='color: #666666; font-size: 14px;'>Reference:</strong></td>
                                                        <td style='padding: 5px 0; text-align: right;'><span style='color: #0F1B2D; font-size: 14px;'>{$reference}</span></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style='margin: 20px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;'>
                                        Please try again or contact our support team if you need assistance.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background-color: #f5f5f5; padding: 20px 30px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; color: #666666; font-size: 12px;'>
                                        Need help? Contact us at support@brightlightministry.org
                                    </p>
                                    <p style='margin: 0; color: #999999; font-size: 11px;'>
                                        Bright Light Ministry Int'l
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
    
    /**
     * Get verification email HTML template
     */
    private function getVerificationEmailHtml($firstName, $verificationCode) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify your email address</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f5;'>
            <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 40px 20px;'>
                        <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #D4AF37 0%, #B8941F 100%); padding: 40px 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #1a1a2e; font-size: 28px; font-family: Georgia, serif;'>Bright Light Ministry Int'l</h1>
                                    <p style='margin: 10px 0 0 0; color: #1a1a2e; font-size: 14px;'>Partnership Portal</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <h2 style='margin: 0 0 20px 0; color: #1a1a2e; font-size: 24px;'>Verify Your Email Address</h2>
                                    <p style='margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.6;'>
                                        Hello {$firstName},<br><br>
                                        Thank you for signing up for the Bright Light Ministry Int'l Partnership Portal. To complete your registration, please use the verification code below:
                                    </p>
                                    <table role='presentation' style='width: 100%; border: 2px solid #D4AF37; border-radius: 8px; padding: 30px; margin: 20px 0;'>
                                        <tr>
                                            <td style='text-align: center;'>
                                                <p style='margin: 0 0 10px 0; color: #666666; font-size: 14px;'>Your Verification Code</p>
                                                <p style='margin: 0; color: #D4AF37; font-size: 48px; font-family: Georgia, serif; letter-spacing: 8px; font-weight: bold;'>{$verificationCode}</p>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style='margin: 20px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;'>
                                        This code will expire in 24 hours. If you did not create an account, please ignore this email.
                                    </p>
                                    <p style='margin: 10px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;'>
                                        <strong>Need help?</strong> Contact us at <a href='mailto:support@brightlightministry.org' style='color: #D4AF37;'>support@brightlightministry.org</a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background-color: #f5f5f5; padding: 20px 30px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; color: #666666; font-size: 12px;'>
                                        Thank you for joining our partnership community!
                                    </p>
                                    <p style='margin: 0; color: #999999; font-size: 11px;'>
                                        Bright Light Ministry Int'l | support@brightlightministry.org
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
    
    /**
     * Get subscription cancelled HTML template
     */
    private function getSubscriptionCancelledHtml($firstName, $subscriptionId) {
        $subscriptionIdHtml = $subscriptionId ? "<table role='presentation' style='width: 100%; border: 1px solid #6c757d; border-radius: 8px; padding: 20px; margin: 20px 0;'><tr><td style='text-align: center;'><strong style='color: #666666; font-size: 14px;'>Subscription ID:</strong><br><span style='color: #0F1B2D; font-size: 14px;'>".htmlspecialchars($subscriptionId)."</span></td></tr></table>" : '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Subscription cancelled</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f5;'>
            <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 40px 20px;'>
                        <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); padding: 40px 30px; text-align: center;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-family: Georgia, serif;'>Bright Light Ministry Int'l</h1>
                                    <p style='margin: 10px 0 0 0; color: #E7D9B4; font-size: 14px;'>Partnership Portal</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <h2 style='margin: 0 0 20px 0; color: #6c757d; font-size: 24px;'>Subscription Cancelled</h2>
                                    <p style='margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.6;'>
                                        Hello {$firstName},<br><br>
                                        Your recurring donation subscription has been cancelled. Thank you for your past support.
                                    </p>
                                    {$subscriptionIdHtml}
                                    <p style='margin: 20px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;'>
                                        If you would like to restart your recurring donation, please visit our donation page.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background-color: #f5f5f5; padding: 20px 30px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; color: #666666; font-size: 12px;'>
                                        Thank you for your continued support!
                                    </p>
                                    <p style='margin: 0; color: #999999; font-size: 11px;'>
                                        Bright Light Ministry Int'l | support@brightlightministry.org
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Get support ticket notification HTML template.
     */
    private function getSupportTicketNotificationHtml($ticketId, $subjectLine, $category, $submittedBy, $submitterEmail, $message, $adminUrl) {
        $adminLinkHtml = '';
        if (!empty($adminUrl)) {
            $safeUrl = htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8');
            $adminLinkHtml = "
                <tr>
                    <td style='padding-top: 24px;'>
                        <a href='{$safeUrl}' style='display: inline-block; background: #C9A24B; color: #0F1B2D; text-decoration: none; font-weight: 600; padding: 14px 22px; border-radius: 10px;'>Open Ticket in Admin</a>
                    </td>
                </tr>
            ";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>New Support Ticket</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f5;'>
            <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 32px 18px;'>
                        <table role='presentation' style='max-width: 640px; margin: 0 auto; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 24px rgba(0,0,0,0.08);'>
                            <tr>
                                <td style='background: linear-gradient(135deg, #122137 0%, #0F1B2D 100%); padding: 32px 28px;'>
                                    <p style='margin: 0 0 8px; color: #E7D9B4; font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase;'>Admin Notification</p>
                                    <h1 style='margin: 0; color: #FAF6EF; font-size: 28px; font-family: Georgia, serif;'>New Support Ticket Submitted</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 28px;'>
                                    <table role='presentation' style='width: 100%; border-collapse: collapse;'>
                                        <tr>
                                            <td style='padding: 10px 0; color: #666666; font-size: 14px; width: 36%;'><strong>Ticket ID</strong></td>
                                            <td style='padding: 10px 0; color: #0F1B2D; font-size: 14px;'>#".htmlspecialchars((string) $ticketId, ENT_QUOTES, 'UTF-8')."</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px 0; color: #666666; font-size: 14px;'><strong>Subject</strong></td>
                                            <td style='padding: 10px 0; color: #0F1B2D; font-size: 14px;'>".htmlspecialchars((string) $subjectLine, ENT_QUOTES, 'UTF-8')."</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px 0; color: #666666; font-size: 14px;'><strong>Category</strong></td>
                                            <td style='padding: 10px 0; color: #0F1B2D; font-size: 14px;'>".htmlspecialchars(ucwords(str_replace('_', ' ', (string) $category)), ENT_QUOTES, 'UTF-8')."</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px 0; color: #666666; font-size: 14px;'><strong>Submitted By</strong></td>
                                            <td style='padding: 10px 0; color: #0F1B2D; font-size: 14px;'>".htmlspecialchars((string) $submittedBy, ENT_QUOTES, 'UTF-8')."</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px 0; color: #666666; font-size: 14px;'><strong>Email</strong></td>
                                            <td style='padding: 10px 0; color: #0F1B2D; font-size: 14px;'>".htmlspecialchars((string) $submitterEmail, ENT_QUOTES, 'UTF-8')."</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 18px 0 8px; color: #666666; font-size: 14px; vertical-align: top;'><strong>Message</strong></td>
                                            <td style='padding: 18px 0 8px; color: #0F1B2D; font-size: 14px; line-height: 1.65;'>".nl2br(htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'))."</td>
                                        </tr>
                                        {$adminLinkHtml}
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
