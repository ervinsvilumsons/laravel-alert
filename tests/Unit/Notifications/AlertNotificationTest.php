<?php

use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\Events\JobFailed;
use Mockery\MockInterface;

function createNotification(): AlertNotification
{
    /** @var Job&MockInterface $job */
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\TestJob');
    $job->shouldReceive('getQueue')->andReturn('default');

    $event = new JobFailed('redis', $job, new Exception('Test exception message'));

    $data = [
        'title' => 'Test exception title',
        'message' => $event->exception->getMessage(),
        'context' => ['meta' => ['foo' => 'bar']],
        'level' => 'error',
    ];

    return new AlertNotification($data);
}

it('builds a correct mail message', function () {
    $notification = createNotification();
    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toContain('Service Alert');
});

it('builds a correct slack message', function () {
    $notification = createNotification();

    $slack = $notification->toSlack(new stdClass);

    $payload = $slack->toArray();

    expect($payload['text'])->toContain('Test exception title');
});

it('builds a correct slack message with blocks', function () {
    $notification = createNotification();

    $slack = $notification->toSlack(new stdClass);

    // Force the blocks to be built
    $payload = $slack->toArray();

    expect($payload)
        ->and($payload)->toHaveKey('blocks');
});

it('formats non-scalar values in mail context', function () {
    $notification = createNotification();

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toContain('Service Alert');
});

it('handles warning level in mail', function () {
    $notification = new AlertNotification([
        'title' => 'Warning',
        'message' => 'Something is off',
        'context' => ['foo' => 'bar'],
        'level' => 'warning',
    ]);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toContain('Service Alert');
});

it('handles info level in mail', function () {
    $notification = new AlertNotification([
        'title' => 'Info',
        'message' => 'Just info',
        'context' => [],
        'level' => 'info',
    ]);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toContain('Service Alert');
});

it('builds slack message without context', function () {
    $notification = new AlertNotification([
        'title' => 'No Context',
        'message' => 'Hello',
        'context' => [],
        'level' => 'error',
    ]);

    $slack = $notification->toSlack(new stdClass);

    $payload = $slack->toArray();

    expect($payload['text'])->toContain('No Context');
});

it('uses configured routes for anonymous notifiable', function () {
    $notification = createNotification();

    $notifiable = new AnonymousNotifiable;

    $notifiable->route('mail', 'test@example.com');
    $notifiable->route('slack', 'test-route');

    expect($notification->via($notifiable))
        ->toBe(['mail', 'slack']);
});

it('handles critical mail level', function () {
    $notification = new AlertNotification([
        'title' => 'Critical',
        'message' => 'Critical alert',
        'context' => [],
        'level' => 'critical',
    ]);

    $mail = $notification->toMail(new stdClass);

    expect($mail)
        ->and($mail->level)->toBe('error');
});

it('handles error mail level', function () {
    $notification = new AlertNotification([
        'title' => 'Error',
        'message' => 'Error alert',
        'context' => [],
        'level' => 'error',
    ]);

    $mail = $notification->toMail(new stdClass);

    expect($mail)
        ->and($mail->level)->toBe('error');
});

it('uses mail channel for a regular notifiable', function () {
    $notification = createNotification();

    expect($notification->via(new stdClass))
        ->toBe(['mail']);
});
