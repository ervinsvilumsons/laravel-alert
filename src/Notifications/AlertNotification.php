<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Facades\Config;

class AlertNotification extends Notification
{
    const string SUBJECT = 'Service Alert';

    /**
     * @param  array{title: string, message: string, context: array<string, mixed>, level: string}  $data
     */
    public function __construct(public array $data) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return array_values(
            array_unique(
                array_filter(
                    array_keys(Config::array('alert-manager.channels', ['mail' => []])),
                    is_string(...),
                )
            )
        );
    }

    private function formatValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(self::SUBJECT)
            ->line($this->data['message']);

        foreach ($this->data['context'] as $key => $value) {
            $mail->line('**'.(string) $key.':** '.$this->formatValue($value));
        }

        return match ($this->data['level']) {
            'critical', 'error' => $mail->error(),
            'warning' => $mail->level('warning'),
            default => $mail,
        };
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $slack = (new SlackMessage)
            ->text($this->data['title'])
            ->headerBlock($this->data['title'])
            ->sectionBlock(function (SectionBlock $block): void {
                $block->text($this->data['message']);
            });

        if (! empty($this->data['context'])) {
            $slack->sectionBlock(function (SectionBlock $block): void {
                $text = '';
                foreach ($this->data['context'] as $key => $value) {
                    $text .= "*{$key}:* ".$this->formatValue($value)."\n";
                }
                $block->text($text);
            });
        }

        $slack->contextBlock(function (ContextBlock $block): void {
            $block->text('Level: '.strtoupper($this->data['level']));
        });

        return $slack;
    }
}
