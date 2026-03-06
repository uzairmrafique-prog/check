<?php

use Mpdf\Mpdf;
use Carbon\Carbon;
use App\Jobs\SendMail;
use App\Models\EmailTemplate;
use Illuminate\Support\Str;
use Mpdf\Output\Destination;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

if (!function_exists('settings')) {
    function settings()
    {
        return new App\Models\Setup\Setting;
    }
}

if (!function_exists('counter')) {
    function counter()
    {
        return new App\Models\Setup\Counter;
    }
}

if (!function_exists('pdf')) {
    function pdf($file, $model, $file_name, $orientation = 'P', $autoMargin = false)
    {
        $pdf = pdfRaw($file, $model, $orientation, $autoMargin);
        $file = $file_name . '-' . time() . '.pdf';

        if (request()->has('mode') && request()->mode == 'download') {
            return $pdf->Output($file, Destination::DOWNLOAD);
        }

        return $pdf->Output($file, Destination::INLINE);
    }
}

function pdfRaw($file, $model, $orientation = 'P', $autoMargin = false)
{
    $html = view($file, ['model' => $model]);
    $pdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-' . $orientation,
        'default_font' => "arial narrow",
        'margin_left' => 5,
        'margin_right' => 5,
        'setAutoTopMargin' => $autoMargin,
        'setAutoBottomMargin' => $autoMargin,
    ]);

    $footerDesign = [
        'C' => [
            'content' => 'Page {PAGENO} of {nbpg}',
            'font-size' => 7,
            'font-style' => 'I',
            'font-family' => 'serif',
            'color' => '#363636'
        ],
        'R' => [
            'content' => Auth::user()->name . ' | ' . Carbon::now()->format('d-m-Y h:i:s a'),
            'font-size' => 7,
            'font-style' => 'I',
            'font-family' => 'serif',
            'color' => '#363636'
        ],
    ];
    $pdf->SetFooter($footerDesign, 'O');
    ini_set('memory_limit', '150000M');
    ini_set("pcre.backtrack_limit", "1000000000");
    $pdf->WriteHTML($html);

    return $pdf;
}

if (! function_exists('email_template')) {
    function email_template($name)
    {
        $email_template = EmailTemplate::where('name', $name)->first();
        return $email_template;
    }
}

if (!function_exists('send_mail')) {
    function send_mail($directory, $data, $name, $email, $subject, $cc = [])
    {
        dispatch(new SendMail($directory, $data, $email, $name, $subject, $cc));
    }
}

// if (!function_exists('save_image')) {
//     function save_image($file, $model, $value, $directory = 'uploads/images', $width = 800, $height = 600)
//     {
//         $validator = Validator::make(
//             ['file' => $file],
//             ['file' => 'required|image|mimes:jpg,jpeg,png,svg']
//         );

//         if ($validator->fails()) {
//             return $validator->errors();
//         }

//         if (!$file->isValid()) {
//             return null;
//         }

//         $manager = new ImageManager(new GdDriver());

//         $image = $manager->read($file->getPathname());
//         $image->resize($width, $height, function ($constraint) {
//             $constraint->aspectRatio();
//             $constraint->upsize();
//         });

//         $fileSizeKB = $file->getSize() / 1024;
//         $quality = $fileSizeKB < 200 ? 90 : 60;

//         $extension = $file->getClientOriginalExtension() ?: 'jpg';
//         $fileName  = Str::random(10) . '.' . $extension;
//         $path      = $directory . '/' . $fileName;

//         $encodedImage = match (strtolower($extension)) {
//             'jpg', 'jpeg' => $image->toJpeg($quality),
//             'png'         => $image->toPng(true),
//             default       => $image->toJpeg($quality),
//         };

//         Storage::disk('public')->put($path, (string) $encodedImage);

//         return $model->images()->create([
//             'name' => $fileName,
//             'type' => $file->getClientMimeType(),
//             'size' => $file->getSize(),
//             'path' => $path,
//             'value' => $value,
//         ]);
//     }
// }

if (!function_exists('save_image')) {
    function save_image($file, $model, $value, $directory = 'uploads/images', $width = 800, $height = 600)
    {
        $validator = Validator::make(
            ['file' => $file],
            ['file' => 'required|mimes:jpg,jpeg,png,svg']
        );

        if ($validator->fails()) {
            return $validator->errors();
        }

        if (!$file->isValid()) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $fileName  = Str::random(10) . '.' . $extension;
        $path      = $directory . '/' . $fileName;

        // Ensure directory exists
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        if ($extension === 'svg') {
            // For SVG, just store the file as-is
            Storage::disk('public')->put($path, file_get_contents($file->getPathname()));
        } else {
            // For raster images, resize and encode
            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($file->getPathname());
            $image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $fileSizeKB = $file->getSize() / 1024;
            $quality = $fileSizeKB < 200 ? 90 : 60;

            $encodedImage = match ($extension) {
                'jpg', 'jpeg' => $image->toJpeg($quality),
                'png'         => $image->toPng(true),
                default       => $image->toJpeg($quality),
            };

            Storage::disk('public')->put($path, (string) $encodedImage);
        }

        return $model->images()->create([
            'name' => $fileName,
            'type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
            'value' => $value,
        ]);
    }
}


if (!function_exists('booking_overlap')) {
    /**
     * Check if a given court has an overlapping booking on a given date.
     *
     * @param int $courtId
     * @param string $bookingDate   Format: 'Y-m-d'
     * @param string $startTime     Format: 'H:i' or 'H:i:s'
     * @param string|null $endTime  Format: 'H:i' or 'H:i:s', optional
     * @return bool                 True if there is an overlap
     */
    function booking_overlap(int $courtId, string $bookingDate, string $startTime, ?string $endTime = null): bool
    {
        $start = Carbon::parse($startTime);
        $end   = $endTime ? Carbon::parse($endTime) : null;

        $existingBookings = \App\Models\BookingManagement\CourtBooking::where('court_id', $courtId)
            ->where('booking_date', $bookingDate)
            ->whereHas('summary', function ($q) {
                $q->whereIn('status', [1, 4]);
            })
            ->get();

        foreach ($existingBookings as $booking) {
            $bookingStart = Carbon::parse($booking->start_time);
            $bookingEnd   = $booking->end_time ? Carbon::parse($booking->end_time) : null;

            // If both have end times
            if ($end && $bookingEnd && $start->lt($bookingEnd) && $end->gt($bookingStart)) {
                return true;
            }

            // If either is open-ended
            if (!$end && (!$bookingEnd || $start->lt($bookingEnd))) {
                return true;
            }

            if ($end && !$bookingEnd && $end->gt($bookingStart)) {
                return true;
            }
        }

        return false;
    }
}
