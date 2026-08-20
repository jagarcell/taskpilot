<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberInvited extends Notification
{
    use Queueable;

    public function __construct(protected Project $project, protected User $inviter)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->project;

        return (new MailMessage)
            ->subject("You've been added to the project: {$project->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->inviter->name} added you to the project \"{$project->name}\".")
            ->action('View project', url("/projects/{$project->id}"))
            ->line('If you did not expect this, please contact your team administrator.');
    }
}
