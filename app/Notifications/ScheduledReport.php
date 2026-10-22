<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ScheduledReport extends Notification implements ShouldQueue
{
    use Queueable;

    public array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $tenantName = $this->reportData['tenant']['name'] ?? 'Unknown';
        $period = $this->reportData['period'] ?? 'Unknown';
        $mrr = $this->reportData['revenue']['mrr'] ?? 0;
        $totalCampaigns = $this->reportData['campaign_stats']['campaign_stats']['total'] ?? 0;
        $activeCampaigns = $this->reportData['campaign_stats']['campaign_stats']['active'] ?? 0;
        $totalTasks = $this->reportData['task_stats']['task_stats']['total'] ?? 0;
        $completedTasks = $this->reportData['task_stats']['task_stats']['completed'] ?? 0;

        return (new MailMessage)
            ->subject("Monthly Report for {$tenantName} - {$period}")
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line("Your monthly report for **{$tenantName}** ({$period}) has been generated.")
            ->line("**Key Metrics:**")
            ->line("- Monthly Recurring Revenue: \${$mrr}")
            ->line("- Total Campaigns: {$totalCampaigns} (Active: {$activeCampaigns})")
            ->line("- Total Tasks: {$totalTasks} (Completed: {$completedTasks})")
            ->action('View Full Report', config('app.frontend_url', 'http://localhost:3000') . '/reports')
            ->line('You can also view historical reports in the Reports section of your dashboard.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'scheduled_report',
            'message' => "Monthly report generated for {$this->reportData['tenant']['name']}",
            'report_data' => $this->reportData,
        ];
    }
}
