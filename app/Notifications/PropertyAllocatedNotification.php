<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PropertyAllocatedNotification extends Notification
{
    use Queueable;

    protected $estateName;
    protected $plotIds;
    protected $allocationReference;

    public function __construct(string $estateName, array $plotIds, string $allocationReference)
    {
        $this->estateName = $estateName;
        $this->plotIds = $plotIds;
        $this->allocationReference = $allocationReference;
    }

    /**
     * Notification channels
     */
    public function via($notifiable)
    {
        return ['database', 'mail']; // you can remove mail if not needed
    }

    /**
     * Email notification
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Property Successfully Allocated')
            ->greeting('Hello ' . ($notifiable->name ?? ''))
            ->line("Your property has been successfully allocated.")
            ->line("Estate: {$this->estateName}")
            ->line("Plot ID(s): " . implode(', ', $this->plotIds))
            ->line("Allocation Reference: {$this->allocationReference}")
            ->action('View Property on Portal', url('/dashboard/properties'))
            ->line('You can view your property details and download related documents from your dashboard.');
    }

    /**
     * Database notification
     */
    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Property Allocated',
            'message' => "Your property at {$this->estateName} has been allocated.",
            'estate' => $this->estateName,
            'plot_ids' => $this->plotIds,
            'allocation_reference' => $this->allocationReference,
            'action_url' => '/dashboard/properties',
        ];
    }
}
