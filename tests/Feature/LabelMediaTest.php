<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\LabelService;
use App\Services\WorkflowException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Label Tom & Jerry No.103 (64 × 32 mm, 2 × 5) dengan kalibrasi posisi & lembar uji. */
class LabelMediaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_tj103_profile_calibration_and_test_sheet(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $p = LabelService::profile('tj-103');
        $this->assertSame([64, 32, 2, 5], [$p['label_w'], $p['label_h'], $p['cols'], $p['rows']]);
        $this->assertSame(10, app(LabelService::class)->layout(23, 'tj-103')['per_sheet']);
        $this->assertSame(3, app(LabelService::class)->layout(23, 'tj-103')['sheets']);

        LabelService::saveCalibration('tj-103', ['margin_left' => 12.5, 'margin_top' => 15, 'gap_x' => 5, 'gap_y' => 2, 'cols' => 2, 'rows' => 5], $admin);
        AppSetting::flush();
        $p = LabelService::profile('tj-103');
        $this->assertSame([12.5, 15.0, 5.0, 2.0], [$p['margin_left'], $p['margin_top'], $p['gap_x'], $p['gap_y']]);

        $this->expectException(WorkflowException::class);
        LabelService::saveCalibration('tj-103', ['margin_left' => 100, 'cols' => 2], $admin); // 100 + 2×64 > 210
    }

    public function test_test_sheet_pdf_and_batch_on_tj103(): void
    {
        User::query()->update(['must_change_password' => false]);
        $u = User::where('email', 'eng.admin@grandpasundanhotel.com')->firstOrFail();
        $this->actingAs($u);
        $this->get(route('labels.test', 'tj-103'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $labels = app(LabelService::class)->select(['include_unverified' => true])->take(12);
        $batch = app(LabelService::class)->createBatch($labels, 'tj-103', 1, $u);
        $this->assertSame((int) ceil($labels->count() / 10), $batch->sheet_count);
        $this->get(route('labels.pdf', $batch))->assertOk();
        \Livewire\Livewire::test(\App\Filament\Pages\CetakLabel::class)->assertSet('profile', 'tj-103')
            ->set('cal.margin_left', 14)->call('saveCal')->assertSet('cal.margin_left', 14.0);
    }
}
