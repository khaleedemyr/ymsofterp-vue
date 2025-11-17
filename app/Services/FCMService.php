<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    private $serverKey;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct()
    {
        // Try to get from .env first, if not set, use the existing key
        $this->serverKey = env('FCM_SERVER_KEY', 'AAAAslzPpRc:APA91bEHothpRmZG8xt9mkS_mqMD8dRJhxAwGnv-7eLudDdfydMBo12cw31GEFYQN7c0tsGbi22Wa3gqObbE17pBmTDXpmxUwtkdN7hqEkpgLxgVCFKkdH--RcpfiN3E1LyXCr1LHRSc');
        
        if (!$this->serverKey) {
            Log::warning('FCM_SERVER_KEY is not set');
        }
    }

    /**
     * Send notification to a single device
     */
    public function sendToDevice($deviceToken, $title, $body, $data = [], $imageUrl = null)
    {
        if (!$this->serverKey) {
            return [
                'success' => false,
                'error' => 'FCM Server Key not configured'
            ];
        }

        $payload = [
            'to' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => array_merge($data, [
                'photo' => $imageUrl // Keep compatibility with existing format
            ]),
            'priority' => 'high'
        ];

        if ($imageUrl) {
            $payload['notification']['image'] = $imageUrl;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            $result = $response->json();

            return [
                'success' => $response->successful(),
                'response' => $result,
                'message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('FCM Send Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to multiple devices
     */
    public function sendToMultipleDevices($deviceTokens, $title, $body, $data = [], $imageUrl = null)
    {
        if (!$this->serverKey) {
            return [
                'success' => false,
                'error' => 'FCM Server Key not configured'
            ];
        }

        // FCM supports up to 1000 tokens per request
        $chunks = array_chunk($deviceTokens, 1000);
        $results = [];

        foreach ($chunks as $chunk) {
            $payload = [
                'registration_ids' => $chunk,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
                'priority' => 'high'
            ];

            if ($imageUrl) {
                $payload['notification']['image'] = $imageUrl;
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $this->serverKey,
                    'Content-Type' => 'application/json',
                ])->post($this->fcmUrl, $payload);

                $result = $response->json();
                $results[] = [
                    'success' => $response->successful(),
                    'response' => $result,
                    'success_count' => $result['success'] ?? 0,
                    'failure_count' => $result['failure'] ?? 0
                ];
            } catch (\Exception $e) {
                Log::error('FCM Send Multiple Error: ' . $e->getMessage());
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Send notification to topic (for broadcast)
     */
    public function sendToTopic($topic, $title, $body, $data = [], $imageUrl = null)
    {
        if (!$this->serverKey) {
            return [
                'success' => false,
                'error' => 'FCM Server Key not configured'
            ];
        }

        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => $data,
            'priority' => 'high'
        ];

        if ($imageUrl) {
            $payload['notification']['image'] = $imageUrl;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            $result = $response->json();

            return [
                'success' => $response->successful(),
                'response' => $result,
                'message_id' => $result['message_id'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('FCM Send Topic Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

