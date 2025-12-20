<?php

namespace App\Jobs;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsDeviceToken;
use App\Models\MemberAppsPushNotification;
use App\Models\MemberAppsPushNotificationRecipient;
use App\Models\MemberAppsNotification;
use App\Services\FCMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMemberNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Retry 3 times if failed
    public $timeout = 300; // 5 minutes timeout per job

    protected $notificationId;
    protected $memberIds;
    protected $title;
    protected $message;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct($notificationId, $memberIds, $title, $message, $data = [])
    {
        $this->notificationId = $notificationId;
        $this->memberIds = $memberIds; // Array of member IDs to process in this job
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $fcmService = app(FCMService::class);
        $notification = MemberAppsPushNotification::find($this->notificationId);

        if (!$notification) {
            Log::error('Notification not found in job', [
                'notification_id' => $this->notificationId,
            ]);
            return;
        }

        $processedCount = 0;
        $successCount = 0;
        $failedCount = 0;

        // Process members in chunks to avoid memory issues
        $memberChunks = array_chunk($this->memberIds, 50); // Process 50 members per chunk

        foreach ($memberChunks as $memberChunk) {
            $members = MemberAppsMember::whereIn('id', $memberChunk)
                ->where('is_active', true)
                ->where('allow_notification', true)
                ->get();

            foreach ($members as $member) {
                try {
                    // Get device tokens for this member
                    $deviceTokens = MemberAppsDeviceToken::where('member_id', $member->id)
                        ->where('is_active', true)
                        ->get();

                    if ($deviceTokens->isEmpty()) {
                        // Create recipient record with failed status
                        MemberAppsPushNotificationRecipient::create([
                            'notification_id' => $this->notificationId,
                            'member_id' => $member->id,
                            'status' => 'failed',
                            'error_message' => 'No active device token',
                        ]);
                        $failedCount++;
                        $processedCount++;
                        continue;
                    }

                    // Send notification using sendToMember
                    $result = $fcmService->sendToMember(
                        $member,
                        $this->title,
                        $this->message,
                        array_merge($this->data, [
                            'notification_id' => $this->notificationId,
                        ])
                    );

                    $memberSuccessCount = $result['success_count'] ?? 0;
                    $memberFailedCount = $result['failed_count'] ?? 0;

                    // Create recipient records for each device token
                    foreach ($deviceTokens as $deviceToken) {
                        $status = ($memberSuccessCount > 0) ? 'sent' : 'failed';
                        $errorMessage = ($memberSuccessCount > 0) ? null : 'FCM send failed';

                        MemberAppsPushNotificationRecipient::create([
                            'notification_id' => $this->notificationId,
                            'member_id' => $member->id,
                            'device_token_id' => $deviceToken->id,
                            'status' => $status,
                            'error_message' => $errorMessage,
                        ]);
                    }

                    // Save to member_apps_notifications for read tracking (only if successful)
                    if ($memberSuccessCount > 0) {
                        // Check if notification already exists for this member and notification_id
                        $existingNotification = MemberAppsNotification::where('member_id', $member->id)
                            ->where('type', 'manual_notification')
                            ->whereRaw('JSON_EXTRACT(data, "$.notification_id") = ?', [$this->notificationId])
                            ->first();

                        if (!$existingNotification) {
                            MemberAppsNotification::create([
                                'member_id' => $member->id,
                                'type' => 'manual_notification',
                                'title' => $this->title,
                                'message' => $this->message,
                                'data' => array_merge($this->data, [
                                    'type' => 'manual_notification',
                                    'notification_id' => $this->notificationId,
                                ]),
                                'is_read' => false,
                            ]);
                        }
                    }

                    $successCount += $memberSuccessCount;
                    $failedCount += $memberFailedCount;
                    $processedCount++;

                    // Small delay to avoid overwhelming FCM API
                    if ($processedCount % 10 === 0) {
                        usleep(100000); // 0.1 second delay every 10 members
                    }

                } catch (\Exception $e) {
                    Log::error('Error processing member in notification job', [
                        'notification_id' => $this->notificationId,
                        'member_id' => $member->id,
                        'error' => $e->getMessage(),
                    ]);

                    // Mark all device tokens as failed
                    foreach ($deviceTokens as $deviceToken) {
                        MemberAppsPushNotificationRecipient::create([
                            'notification_id' => $this->notificationId,
                            'member_id' => $member->id,
                            'device_token_id' => $deviceToken->id,
                            'status' => 'failed',
                            'error_message' => 'Exception: ' . $e->getMessage(),
                        ]);
                    }

                    $failedCount += $deviceTokens->count();
                    $processedCount++;
                }
            }
        }

        // Update notification counts (using atomic increment to avoid race conditions)
        $notification->increment('sent_count', $successCount);
        $notification->increment('delivered_count', $successCount);

        Log::info('Notification job completed', [
            'notification_id' => $this->notificationId,
            'processed' => $processedCount,
            'success' => $successCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Notification job failed', [
            'notification_id' => $this->notificationId,
            'member_ids' => $this->memberIds,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

