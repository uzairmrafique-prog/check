<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $directory;
    protected $data;
    protected $email;
    protected $name;
    protected $subject;
    protected $cc;

    public $tries = 3;

    public function __construct($directory, $data, $email, $name, $subject, $cc = [])
    {
        $this->directory = $directory;
        $this->data = $data;
        $this->email = $email;
        $this->name = $name;
        $this->subject = $subject;
        $this->cc = $cc;
    }

    public function handle(): void
    {
        Mail::send($this->directory, $this->data, function ($message){
            $message->to($this->email, $this->name)->subject($this->subject);

            // Add CC if there are any CC emails
            if (!empty($this->cc)) {
                $message->cc($this->cc);
            }
        });
    }
}
