<?php

namespace Tests\Feature;

use App\Mail\AppointmentReceived;
use App\Models\Appointment;
use App\Services\MicrosoftGraph\GraphClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GraphMailTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_is_sent_through_microsoft_graph(): void
    {
        config([
            'mail.default' => 'microsoft-graph',
            'mail.from.address' => 'rendez-vous@corp.test',
            'services.microsoft_graph.tenant_id' => 'tenant',
            'services.microsoft_graph.client_id' => 'client',
            'services.microsoft_graph.client_secret' => 'secret',
        ]);
        $this->app->forgetInstance(GraphClient::class);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $appointment = Appointment::factory()->create(['first_name' => 'Awa']);
        Mail::to('awa@bni.ci')->send(new AppointmentReceived($appointment));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.microsoft.com/v1.0/users/rendez-vous%40corp.test/sendMail'
            && $r['message']['toRecipients'][0]['emailAddress']['address'] === 'awa@bni.ci'
            && $r['message']['body']['contentType'] === 'HTML'
            && str_contains($r['message']['body']['content'], 'Awa')
            && str_contains($r['message']['subject'], $appointment->reference));
    }
}
