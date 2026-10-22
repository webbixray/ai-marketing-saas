<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SlaBreached extends Notification implements ShouldQueue
{
    use Queueable;

    public Task $task;
    public string $type; // 'response' or 'resolution'

    public function __construct(Task $task, string $type)
    {
        $this->task = $task;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $typeLabel = $this->type === 'response' ? 'Response' : 'Resolution';
        $slaMinutes = $this->type === 'response' 
            ? $this->task->sla_response_minutes 
            : $this->task->sla_resolution_minutes;

        return (new MailMessage)
            ->subject("SLA Breach Alert: {$typeLabel} Time Exceeded")
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line("The **{$typeLabel} SLA** for task **\"{$this->task->title}\"** has been breached.")
            ->line("**Task Details:**")
            ->line("- Task: {$this->task->title}")
            ->line("- Priority: {$this->task->priority}")
            ->line("- SLA Target: {$slaMinutes} minutes")
            ->line("- Status: {$this->task->status}")
            ->action('View Task', config('app.frontend_url', 'http://localhost:3000') . '/tasks/' . $this->task->id)
            ->line('Please review and take appropriate action.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'sla_breach',
            'message' => "{$this->type} SLA breached for task: {$this->task->title}",
            'task_id' => $this->task->id,
            'breach_type' => $this->type,
        ];
    }
}
