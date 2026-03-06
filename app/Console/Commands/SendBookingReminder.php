<?php

namespace App\Console\Commands;

use App\Models\BookingManagement\CourtBookingSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBookingReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-booking-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //


        $now = Carbon::now();

        $to = $now->copy()->addHours(12);

        $summaries = CourtBookingSummary::where('reminder_sent', 0)
            ->whereHas('slots', function ($q) use ($now, $to) {
                $q->whereRaw(
                    "TIMESTAMP(booking_date, start_time) BETWEEN ? AND ?",
                    [$now, $to]
                );
            })
            ->with(['slots'])
            ->get();


        foreach ($summaries as $summary) {

            if ($summary->slots->isEmpty()) {
                continue;
            }

            $slot = $summary->slots->first(); // already sorted ASC

            $slotStart = Carbon::parse(
                $slot->booking_date . ' ' . $slot->start_time
            );

            $email_template = email_template('Booking Reminder');

            if ($email_template && $email_template->status == 1) {

                $data = [
                    'body' => str_replace(
                        ['{username}', '{booking_date}', '{start_time}'],
                        [$summary->customer_name, $summary->booking_date, $slotStart->format('h:i A')],
                        $email_template->template
                    )
                ];

                send_mail(
                    'mail.send_mail',
                    $data,
                    $summary->customer_name,
                    $summary->customer_email,
                    $email_template->subject
                );

                $summary->update(['reminder_sent' => 1]);
            }
        }
    }
}
