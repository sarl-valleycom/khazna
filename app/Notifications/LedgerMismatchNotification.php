<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LedgerMismatchNotification extends Notification
{
    /**
     * @param  array<int, string>  $problems  The first few problems.
     * @param  int|null  $total  How many there are in all, when more than listed.
     */
    public function __construct(public readonly array $problems, public readonly ?int $total = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Khazna: the ledger does not add up'))
            ->line(__('The daily ledger check found :count problem(s). No money has been changed; please look at them.', ['count' => $this->total ?? count($this->problems)]));

        foreach (array_slice($this->problems, 0, 20) as $problem) {
            $message->line('• '.$problem);
        }

        $all = $this->total ?? count($this->problems);

        if ($all > 20) {
            $message->line(__('…and :more more.', ['more' => $all - 20]));
        }

        return $message;
    }
}
