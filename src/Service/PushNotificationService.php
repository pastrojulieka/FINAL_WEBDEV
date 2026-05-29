<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Google\Client as GoogleClient;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PushNotificationService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $firebaseProjectId = '',
        private ?string $firebaseCredentialsJson = null,
        private ?string $firebaseServerKey = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return ($this->firebaseCredentialsJson !== null && $this->firebaseCredentialsJson !== '' && $this->firebaseProjectId !== '')
            || ($this->firebaseServerKey !== null && $this->firebaseServerKey !== '');
    }

    public function notifyOrderUpdated(Order $order): void
    {
        $customer = $order->getCreatedBy();
        if (!$customer instanceof User) {
            return;
        }

        $title = 'Order Updated';
        $body = sprintf(
            'Your order #%d (%s) was updated. Delivery: %s',
            $order->getId(),
            $order->getProductName() ?? 'Product',
            $order->getDeliveryDate()?->format('M d, Y') ?? 'TBD'
        );

        $this->sendToUser($customer, $title, $body, [
            'type' => 'order_updated',
            'orderId' => (string) $order->getId(),
        ]);
    }

    /**
     * @param array<string, string> $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $token = $user->getFcmToken();
        if (!$token) {
            $this->logger->info('Push skipped: user has no FCM token', [
                'email' => $user->getEmail(),
            ]);

            return;
        }

        if (!$this->isConfigured()) {
            $this->logger->warning('Push skipped: Firebase credentials not configured on server');

            return;
        }

        try {
            if ($this->firebaseCredentialsJson && $this->firebaseProjectId) {
                $this->sendViaHttpV1($token, $title, $body, $data);
            } else {
                $this->sendViaLegacy($token, $title, $body, $data);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Push notification failed: '.$e->getMessage(), [
                'email' => $user->getEmail(),
            ]);
        }
    }

    /**
     * @param array<string, string> $data
     */
    private function sendViaHttpV1(string $deviceToken, string $title, string $body, array $data): void
    {
        $credentials = json_decode($this->firebaseCredentialsJson, true);
        if (!\is_array($credentials)) {
            throw new \RuntimeException('Invalid FIREBASE_CREDENTIALS_JSON');
        }

        $googleClient = new GoogleClient();
        $googleClient->setAuthConfig($credentials);
        $googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $googleClient->fetchAccessTokenWithAssertion();
        $accessToken = $googleClient->getAccessToken()['access_token'] ?? null;

        if (!$accessToken) {
            throw new \RuntimeException('Unable to obtain Firebase access token');
        }

        $message = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'orders',
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        $url = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            $this->firebaseProjectId
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $message,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('FCM HTTP v1 error: '.$response->getContent(false));
        }
    }

    /**
     * @param array<string, string> $data
     */
    private function sendViaLegacy(string $deviceToken, string $title, string $body, array $data): void
    {
        $response = $this->httpClient->request('POST', 'https://fcm.googleapis.com/fcm/send', [
            'headers' => [
                'Authorization' => 'key='.$this->firebaseServerKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'to' => $deviceToken,
                'priority' => 'high',
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'android_channel_id' => 'orders',
                ],
                'data' => $data,
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('FCM legacy error: '.$response->getContent(false));
        }
    }
}
