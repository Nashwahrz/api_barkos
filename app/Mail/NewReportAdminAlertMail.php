<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewReportAdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Laporan Baru: ' . $this->report->reason,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.report.admin_new_report_alert',
            with: [
                'report' => $this->report,
            ],
        );
    }
}
