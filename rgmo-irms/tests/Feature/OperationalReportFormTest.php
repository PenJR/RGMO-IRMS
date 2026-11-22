<?php

namespace Tests\Feature;

use App\Models\OperationalReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalReportFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_save_reopen_and_update_a_harvest_report(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
        $project = Project::create(['name' => 'Rice production', 'code' => 'RICE-2026', 'status' => Project::STATUS_ACTIVE]);
        $payload = [
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'project_id' => $project->id,
            'prepared_by' => 'Project Manager',
            'reviewed_by' => 'Director',
            'noted_by' => 'RGMO Head',
            'rows' => [[
                'particulars' => 'Area 1 - RC 402',
                'date_of_harvest' => '2026-10-15',
                'average_yield_hectare' => '4,120 kg',
                'previous_balance' => '3,500 kg',
                'production' => '12,360 kg',
                'deductions' => '100 kg',
                'ending_balance' => '15,760 kg',
                'remarks' => 'Harvested',
            ]],
        ];

        $this->actingAs($user)->post(route('reports.forms.store', 'harvest-report'), $payload)
            ->assertRedirect();

        $report = OperationalReport::sole();
        $this->assertTrue(Str::isUlid($report->public_id));
        $this->assertSame('Area 1 - RC 402', $report->rows[0]['particulars']);
        $this->actingAs($user)->get(route('reports.forms.record', $report))
            ->assertOk()->assertSee('Area 1 - RC 402')->assertSee('Harvested');

        $payload['rows'][0]['remarks'] = 'Verified harvest';
        $this->actingAs($user)->put(route('reports.forms.update', $report), $payload)
            ->assertRedirect(route('reports.forms.record', $report));
        $this->assertSame('Verified harvest', $report->fresh()->rows[0]['remarks']);
    }
}
