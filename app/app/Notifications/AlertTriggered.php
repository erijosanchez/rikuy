<?php

namespace App\Notifications;

use App\Models\AlertEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifica a los usuarios del tenant que una regla de alerta se disparó.
 * Canal database: queda en la tabla notifications y se puede listar en la app.
 */
class AlertTriggered extends Notification
{
    use Queueable;

    public function __construct(public AlertEvent $event) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_event_id' => $this->event->id,
            'alert_rule_id' => $this->event->alert_rule_id,
            'period' => $this->event->period,
            'measure' => $this->event->measure,
            'change_pct' => $this->event->change_pct,
            'message' => $this->event->message,
        ];
    }
}
