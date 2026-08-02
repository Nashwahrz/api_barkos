<?php

namespace App\Jobs;

use App\Mail\NewReportAdminAlertMail;
use App\Models\Report;
use App\Models\User;
use App\Notifications\NewReportAdminNotification;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyAdminsOfNewReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels;

    public Report $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    public function handle(): void
    {
        try {
            User::where('role', 'super_admin')
                ->whereNotNull('email')
                ->chunk(100, function ($admins) {
                    foreach ($admins as $admin) {
                        try {
                            Mail::to($admin->email)->send(new NewReportAdminAlertMail($this->report));
                            $admin->notify(new NewReportAdminNotification($this->report));
                        } catch (\Exception $e) {
                            Log::error('Failed to send new report admin alert to: ' . $admin->email, ['error' => $e->getMessage()]);
                        }
                    }
                });

            Log::info('New report admin alert sent successfully.', ['report_id' => $this->report->id]);
        } catch (\Exception $e) {
            Log::error('New report admin alert job failed.', ['error' => $e->getMessage()]);
        }
    }
}
