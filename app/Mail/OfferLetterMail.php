<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OfferLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public $candidate_name;
    public $designation;
    public $salary;
    public $joining_date;
    public $pdf;

    public function __construct($candidateName, $offer, $pdfContent)
{
    $this->candidate_name = $candidateName;
    $this->designation    = $offer->position;
    $this->salary         = $offer->salary;
    $this->joining_date   = $offer->start_date;
    $this->pdf            = $pdfContent;
}


    public function build()
    {
        return $this->subject('Your Job Offer Letter')
            ->view('emails.offer_letter')
            ->with([
                'candidate_name' => $this->candidate_name,
                'designation'    => $this->designation,
                'salary'         => $this->salary,
                'joining_date'   => $this->joining_date,
            ])
            ->attachData($this->pdf, 'Offer-Letter.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
