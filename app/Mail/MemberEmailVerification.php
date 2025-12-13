<?php

namespace App\Mail;

use App\Models\MemberAppsMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MemberEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $member;
    public $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(MemberAppsMember $member, string $verificationUrl)
    {
        $this->member = $member;
        $this->verificationUrl = $verificationUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Verify Your Email Address - Justus Group')
                    ->view('emails.member-verification')
                    ->with([
                        'member' => $this->member,
                        'verificationUrl' => $this->verificationUrl,
                    ]);
    }
}

