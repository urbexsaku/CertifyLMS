<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
