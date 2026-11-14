<?php

namespace App\Actions\Dues;

use App\Models\Due;
use App\Models\DueReminder;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;

class BuildDueReminder
{
    /**
     * Fill the reminder template for a due, log it, and return the wa.me link.
     * The browser opens the link; nothing is sent from the server.
     *
     * @return array{url: string, message: string}
     */
    public function handle(User $actor, Due $due): array
    {
        $due->loadMissing(['client', 'contract']);

        $client = $due->client;
        $phone = Phone::normalize($client?->phone);

        if ($client === null || $phone === null) {
            throw ValidationException::withMessages([
                'phone' => __('This client has no valid phone number, so no WhatsApp reminder can be prepared.'),
            ]);
        }

        $template = trim((string) Setting::current(Setting::REMINDER_TEMPLATE, ''));

        if ($template === '') {
            $template = (string) config('khazna.reminder_template');
        }

        $symbol = (string) Setting::current(Setting::CURRENCY_LABEL, (string) config('khazna.currency_symbol'));

        $message = strtr($template, [
            '{client}' => $client->name,
            '{amount}' => Money::fromCents($due->remainingCents(), 'DZD', $symbol)->format(),
            '{contract}' => $this->subject($due),
            '{date}' => $due->due_on->format('d M Y'),
        ]);

        DueReminder::query()->create([
            'due_id' => $due->id,
            'user_id' => $actor->id,
            'channel' => 'whatsapp',
            'template' => $template,
            'message' => $message,
            'phone' => $phone,
        ]);

        return [
            'url' => 'https://wa.me/'.$phone.'?text='.rawurlencode($message),
            'message' => $message,
        ];
    }

    private function subject(Due $due): string
    {
        if ($due->description !== null && $due->description !== '') {
            return $due->description;
        }

        return $due->contract !== null ? $due->contract->type->label() : $due->kind->label();
    }
}
