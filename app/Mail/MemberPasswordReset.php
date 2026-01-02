<?php

namespace App\Mail;

use App\Models\MemberAppsMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MemberPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $member;
    public $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(MemberAppsMember $member, string $resetUrl)
    {
        $this->member = $member;
        $this->resetUrl = $resetUrl;
        
        // Don't queue this email - send immediately
        $this->onConnection('sync');
        $this->onQueue(null);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Reset Password - JUSTUS GROUP')
                    ->view('emails.member-password-reset')
                    ->with([
                        'member' => $this->member,
                        'resetUrl' => $this->resetUrl,
                    ]);
    }
}

