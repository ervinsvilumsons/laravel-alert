<?php

use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Queue\Events\JobFailed;
use Mockery;
use Mockery\MockInterface;

function createNotification(): AlertNotification
{
    /** @var Job&MockInterface $job */
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');

    $event = new JobFailed('redis', $job, new Exception('Test exception message'));

    $data = [
        'title' => 'Test excpeption title',
        'message' => $event->exception->getMessage(),
        'context' => ['meta' => ['foo' => 'bar']],
        'level' => 'error',
    ];

    return new AlertNotification($data);
}

it('uses channels from config', function () {
    config(['alert-manager.channels' => ['mail', 'slack']]);

    $notification = createNotification();
    $notifiable = new stdClass;

    expect($notification->via($notifiable))->toBe(['mail', 'slack']);
});

it('builds a correct mail message', function () {
    $notification = createNotification();
    $mail = $notification->toMail(new stdClass);

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toContain('Service Alert');
});

it('builds a correct slack message', function () {
    $notification = createNotification();

    $slack = $notification->toSlack(new stdClass);

    expect($slack)->toBeInstanceOf(SlackMessage::class);
});

it('builds a correct slack message with blocks', function () {
    $notification = createNotification();

    $slack = $notification->toSlack(new stdClass);

    expect($slack)->toBeInstanceOf(SlackMessage::class);

    // Force the blocks to be built
    $payload = $slack->toArray();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('blocks');
});

it('formats non-scalar values in mail context', function () {
    $notification = createNotification();

    $mail = $notification->toMail(new stdClass);

    expect($mail)->toBeInstanceOf(MailMessage::class);
});

it('handles warning level in mail', function () {
    $notification = new AlertNotification([
        'title' => 'Warning',
        'message' => 'Something is off',
        'context' => ['foo' => 'bar'],
        'level' => 'warning',
    ]);

    $mail = $notification->toMail(new stdClass);

    expect($mail)->toBeInstanceOf(MailMessage::class);
});

it('handles info level in mail', function () {
    $notification = new AlertNotification([
        'title' => 'Info',
        'message' => 'Just info',
        'context' => [],
        'level' => 'info',
    ]);

    $mail = $notification->toMail(new stdClass);

    expect($mail)->toBeInstanceOf(MailMessage::class);
});

it('builds slack message without context', function () {
    $notification = new AlertNotification([
        'title' => 'No Context',
        'message' => 'Hello',
        'context' => [],
        'level' => 'error',
    ]);

    $slack = $notification->toSlack(new stdClass);

    expect($slack)->toBeInstanceOf(SlackMessage::class);
});
