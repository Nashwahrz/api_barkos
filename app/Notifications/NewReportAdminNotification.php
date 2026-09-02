<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewReportAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Report $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Mail is sent separately via NewReportAdminAlertMail in the job.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id_laporan,
            'message'   => ($this->report->reporter->nama ?? 'Seorang pengguna') . ' membuat laporan: ' . $this->report->alasan,
            'type'      => 'admin_new_report',
        ];
    }
}
