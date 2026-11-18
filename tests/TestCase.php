<?php

declare(strict_types=1);

namespace Tests;

use App\Services\GoogleCalendarService;
use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->app->bind(GoogleCalendarService::class, function (): GoogleCalendarService {
            return new class extends GoogleCalendarService
            {
                protected function createClient(): Client
                {
                    $client = new Client;

                    $client->setClientId((string) config('services.google.client_id'));
                    $client->setClientSecret((string) config('services.google.client_secret'));
                    $client->setRedirectUri(route('settings.google-calendar.callback'));
                    $client->setHttpClient(new GuzzleClient([
                        'handler' => HandlerStack::create(new MockHandler([])),
                    ]));

                    return $client;
                }
            };
        });
    }
}
