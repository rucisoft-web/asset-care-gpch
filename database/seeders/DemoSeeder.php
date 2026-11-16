<?php

namespace Database\Seeders;

use App\Models\ApprovalLimit;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLabel;
use App\Models\Department;
use App\Models\Location;
use App\Models\Property;
use App\Models\Role;
use App\Models\RoomAssetTemplate;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Vendor;
use App\Support\CurrentProperty;
use Illuminate\Database\Seeder;

/**
 * Data contoh pilot (TERPISAH dari produksi): satu lantai kamar, area kitchen, MICE & utilitas,
 * template Deluxe (6 jenis / 7 unit), akun jabatan GPCH. Struktur & jumlah kamar wajib diverifikasi GPCH.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'GPCH-2026!ganti';

    public function run(): void
    {
        $p = Property::where('code', config('assetcare.property_code', 'GPCH'))->firstOrFail();
        app(CurrentProperty::class)->set($p);
        $dept = fn ($code) => Department::withoutGlobalScopes()->where('property_id', $p->id)->where('code', $code)->value('id');
        $cat = fn ($code) => AssetCategory::withoutGlobalScopes()->where('property_id', $p->id)->where('code', $code)->value('id');

        // ---- pengguna (nama/jabatan mengikuti data Arah GPCH; email usulan — konfirmasi dengan HR)
        $people = [
            ['edp@grandpasundanhotel.com', 'Admin EDP GPCH (isi nama)', 'EDP / IT Manager', 'EDP', ['it-admin']],
            ['gm@grandpasundanhotel.com', 'Ibu Vivera', 'General Manager', 'EXE', ['gm']],
            ['owner@grandpasundanhotel.com', 'Bapak Wimpy', 'Pemilik', 'EXE', ['owner']],
            ['yodi@grandpasundanhotel.com', 'Bapak Yodi S. Ibrahim', 'Direksi', 'EXE', ['owner-rep']],
            ['ca@grandpasundanhotel.com', 'Bapak Indra', 'Chief Accountant', 'ACC', ['finance']],
            ['engineering@grandpasundanhotel.com', 'Bapak Wahyudin', 'Chief Engineer (Plt.)', 'ENG', ['chief-engineering']],
            ['eng.admin@grandpasundanhotel.com', 'Rara', 'Engineering Admin', 'ENG', ['asset-controller']],
            ['teknisi1@grandpasundanhotel.com', 'Teknisi 1 (isi nama)', 'Teknisi Engineering', 'ENG', ['technician']],
            ['teknisi2@grandpasundanhotel.com', 'Teknisi 2 (isi nama)', 'Teknisi Engineering', 'ENG', ['technician']],
            ['fom@grandpasundanhotel.com', 'Bapak Rizki', 'Front Office Manager', 'FO', ['hod', 'front-office']],
            ['fo.supervisor@grandpasundanhotel.com', 'Muhamad Ihsan', 'Front Office Supervisor', 'FO', ['reporter', 'front-office']],
            ['hk@grandpasundanhotel.com', 'Ibu Wina', 'Executive Housekeeper (Plt.)', 'HK', ['hod']],
            ['hk.supervisor@grandpasundanhotel.com', 'Ujang Nur M', 'Housekeeping Supervisor', 'HK', ['reporter']],
            ['fbm@grandpasundanhotel.com', 'Bapak Asep Agus Damil Arifin', 'F&B Manager / Executive Chef', 'KIT', ['hod']],
            ['purchasing@grandpasundanhotel.com', 'Bapak Daniel', 'Purchasing', 'PUR', ['purchasing']],
            ['wiwin@visiperencana.id', 'Bapak I.G. Wiwin Suyasa', 'Auditor Operasional', 'EXE', ['auditor']],
        ];
        $roles = Role::pluck('id', 'slug');
        foreach ($people as [$email, $name, $title, $d, $r]) {
            $u = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'job_title' => $title, 'password' => self::PASSWORD, 'property_id' => $p->id,
                'department_id' => $dept($d), 'is_active' => true, 'must_change_password' => true,
            ]);
            $u->roles()->syncWithoutDetaching(collect($r)->mapWithKeys(fn ($slug) => [$roles[$slug] => ['property_id' => $p->id]])->all());
        }
        User::whereIn('email', ['teknisi1@grandpasundanhotel.com', 'teknisi2@grandpasundanhotel.com'])->update(['labor_rate' => 35000]);

        // ---- batas persetujuan CONTOH (nominal wajib disahkan manajemen sebelum produksi)
        $gm = User::where('email', 'gm@grandpasundanhotel.com')->first();
        $ce = User::where('email', 'engineering@grandpasundanhotel.com')->first();
        $ca = User::where('email', 'ca@grandpasundanhotel.com')->first();
        foreach ([[$ce, 2500000], [$gm, 25000000], [$ca, 10000000]] as [$u, $max]) {
            ApprovalLimit::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'user_id' => $u->id, 'object_type' => 'work_order'], [
                'max_amount' => $max, 'valid_from' => now()->startOfYear(), 'note' => 'CONTOH — nominal wajib disahkan manajemen',
            ]);
        }

        // ---- tipe kamar & lokasi pilot
        $dlx = RoomType::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'code' => 'DLX'], ['name' => 'Deluxe (contoh)', 'bed_config' => 'King']);
        RoomType::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'code' => 'DLX-TW'], ['name' => 'Deluxe Twin (contoh)', 'bed_config' => 'Twin']);
        RoomType::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'code' => 'STE'], ['name' => 'Suite (contoh)', 'bed_config' => 'King']);

        $loc = function (string $code, string $name, string $type, ?Location $parent = null, array $extra = []) use ($p) {
            return Location::withoutGlobalScopes()->updateOrCreate(['property_id' => $p->id, 'code' => $code], $extra + [
                'name' => $name, 'location_type' => $type, 'parent_id' => $parent?->id, 'is_active' => true,
            ]);
        };
        $gu = $loc('GU', 'Gedung Utama', 'building');
        $l1 = $loc('GU-L1', 'Lantai 1', 'floor', $gu, ['floor_label' => '1']);
        $l3 = $loc('GU-L3', 'Lantai 3 (pilot)', 'floor', $gu, ['floor_label' => '3']);
        foreach (range(301, 310) as $no) {
            $loc('GU-L3-'.$no, 'Kamar '.$no, 'room', $l3, ['room_type_id' => $dlx->id, 'room_ref' => (string) $no, 'pms_room_status' => 'vacant']);
        }
        $lobby = $loc('GU-L1-LOBBY', 'Lobby', 'area', $l1);
        $resto = $loc('GU-L1-RESTO', 'Restoran', 'area', $l1);
        $kitchen = $loc('GU-L1-KIT', 'Kitchen Utama', 'area', $l1);
        $pastry = $loc('GU-L1-PASTRY', 'Pastry', 'area', $l1);
        $mice = $loc('MICE', 'Zona MICE', 'zone');
        $dome = $loc('MICE-DOME', 'Malibu Dome', 'venue', $mice);
        $mep = $loc('MEP', 'Zona MEP & utilitas', 'zone');
        $genset = $loc('MEP-GENSET', 'Ruang Genset', 'area', $mep);
        $boiler = $loc('MEP-BOILER', 'Ruang Boiler', 'area', $mep);
        $panel = $loc('MEP-PANEL', 'Ruang Panel Utama', 'area', $mep);

        // ---- template Deluxe: 6 jenis, 7 unit per kamar (contoh rancangan, bukan hasil inventarisasi)
        $tpl = RoomAssetTemplate::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'code' => 'TPL-DLX', 'version' => 1], [
            'name' => 'Perlengkapan standar Deluxe', 'room_type_id' => $dlx->id, 'status' => 'draft',
            'prepared_by' => User::where('email', 'eng.admin@grandpasundanhotel.com')->value('id'),
            'notes' => 'Contoh dari spesifikasi bagian 24. Jumlah, merek & perlengkapan aktual tetap diperiksa di lapangan.',
        ]);
        if ($tpl->items()->doesntExist()) {
            $hk = $dept('HK');
            $eng = $dept('ENG');
            $items = [
                ['ac', 'AC-SPLIT', 'AC kamar', 1, null, $eng, 'medium', 90],
                ['tv-utama', 'TV', 'TV utama', 1, null, $eng, 'low', null],
                ['minibar', 'MINIBAR', 'Minibar', 1, null, $eng, 'low', 180],
                ['telepon', 'TEL', 'Telepon kamar', 1, null, $eng, 'low', null],
                ['bed', 'BED', 'Tempat tidur', 1, null, $hk, 'low', 365],
                ['lampu-meja', 'LAMP', 'Lampu meja', 2, 'Kiri, Kanan', $hk, 'low', null],
            ];
            foreach ($items as $i => [$key, $c, $name, $qty, $pos, $d, $crit, $pm]) {
                $tpl->items()->create(['item_key' => $key, 'category_id' => $cat($c), 'standard_name' => $name, 'quantity' => $qty, 'mode' => 'individual', 'unit' => 'unit', 'positions' => $pos, 'default_department_id' => $d, 'default_criticality' => $crit, 'pm_interval_days' => $pm, 'sort' => $i + 1]);
            }
            $tpl->forceFill(['status' => 'published', 'published_at' => now(), 'reviewed_by' => $ce->id])->saveQuietly();
        }

        Vendor::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'code' => 'V-AC'], ['name' => 'Vendor servis AC (contoh)', 'specialization' => 'AC split & HVAC', 'service_hours' => '08.00–17.00']);
        Vendor::withoutGlobalScopes()->firstOrCreate(['property_id' => $p->id, 'code' => 'V-LIFT'], ['name' => 'Vendor lift (contoh)', 'specialization' => 'Pemeliharaan lift', 'service_hours' => '24 jam (darurat)']);

        // ---- beberapa aset utilitas & kitchen terverifikasi + satu TV lama di kamar 305 (uji pencocokan AC20)
        $mk = function (array $a) use ($p) {
            if ($found = Asset::withoutGlobalScopes()->where('property_id', $p->id)->where('legacy_code', $a['legacy_code'])->first()) {
                return $found;
            }
            $asset = Asset::create($a + ['property_id' => $p->id, 'ownership' => 'milik', 'operational_status' => 'serviceable', 'lifecycle_status' => 'draft', 'verification_status' => 'unverified']);
            AssetLabel::issueFor($asset);

            return $asset;
        };
        $rows = [
            ['GENSET', 'Genset utama', $genset, 'ENG', 'B', 'critical', 'LEG-GEN-01'],
            ['BOILER', 'Boiler air panas', $boiler, 'ENG', 'C', 'critical', 'LEG-BOI-01'],
            ['PANEL', 'Panel LVMDP', $panel, 'ENG', 'B', 'critical', 'LEG-PNL-01'],
            ['CHILL', 'Chiller kitchen 1', $kitchen, 'KIT', 'B', 'high', 'LEG-CHL-01'],
            ['FREEZ', 'Freezer pastry', $pastry, 'KIT', 'C', 'high', 'LEG-FRZ-01'],
            ['PROJ', 'Projector Malibu Dome', $dome, 'SM', 'B', 'medium', 'LEG-PRJ-01'],
            ['SOUND', 'Sound system Malibu Dome', $dome, 'SM', 'B', 'medium', 'LEG-SND-01'],
            ['APAR', 'APAR lobby 01', $lobby, 'SEC', 'A', 'critical', 'LEG-APR-01'],
            ['CCTV', 'CCTV lobby 01', $lobby, 'SEC', 'A', 'high', 'LEG-CTV-01'],
        ];
        foreach ($rows as [$c, $name, $l, $d, $cond, $crit, $legacy]) {
            $a = $mk(['name' => $name, 'category_id' => $cat($c), 'location_id' => $l->id, 'department_id' => $dept($d), 'condition_grade' => $cond, 'criticality' => $crit, 'legacy_code' => $legacy, 'custodian_id' => $ce->id, 'migration_source' => 'Contoh data awal']);
            if ($a->lifecycle_status === 'draft') {
                $a->forceFill(['verification_status' => 'verified', 'verified_by' => $ce->id, 'verified_at' => now()])->save();
                $a->forceFill(['lifecycle_status' => 'active'])->save();
            }
        }
        $mk(['name' => 'TV lama kamar 305', 'category_id' => $cat('TV'), 'location_id' => Location::withoutGlobalScopes()->where('code', 'GU-L3-305')->value('id'), 'department_id' => $dept('ENG'), 'brand' => 'Samsung', 'serial_number' => 'SN-TV-305-LAMA', 'legacy_code' => 'LEG-TV-305', 'migration_source' => 'Register lama']);
        $this->fixedAssetDemo($p, $ce);
        $this->maintenanceDemo($p, $ce);
    }

    /** Contoh rencana PM & pembacaan kondisi (hanya bila belum ada). */
    protected function maintenanceDemo(Property $p, User $ce): void
    {
        if (\App\Models\PmPlan::withoutGlobalScopes()->where('property_id', $p->id)->exists()) {
            return;
        }
        $asset = fn (string $legacy) => Asset::withoutGlobalScopes()->where('property_id', $p->id)->where('legacy_code', $legacy)->first();
        $param = fn (string $code) => \App\Models\ConditionParameter::withoutGlobalScopes()->where('property_id', $p->id)->where('code', $code)->first();
        $today = now(\App\Support\Fmt::tz())->startOfDay();
        $tech = User::where('email', 'teknisi1@grandpasundanhotel.com')->first() ?? $ce;
        $pm = app(\App\Services\PlannedMaintenanceService::class);

        // pembacaan awal (sebelum rencana meter dibuat)
        $gen = $asset('LEG-GEN-01');
        $chill = $asset('LEG-CHL-01');
        if ($gen && $param('JAM-OPS')) {
            $pm->recordReading($gen, $param('JAM-OPS'), 1180, $ce, 'hour meter', $today->copy()->subDays(30));
        }
        $plans = [
            ['PM-GEN-250', 'Servis genset (bulanan / 250 jam)', 'asset', 'LEG-GEN-01', null, 30, 'JAM-OPS', 250, $today->copy()->addDays(5), 'P3',
                ['Cek level oli & coolant', 'Cek tegangan aki & terminal', 'Bersihkan filter udara', 'Uji jalan tanpa beban 15 menit', 'Catat jam operasi']],
            ['PM-BOI-01', 'Inspeksi & blowdown boiler bulanan', 'asset', 'LEG-BOI-01', null, 30, null, null, $today->copy()->addDays(12), 'P3',
                ['Blowdown & cek kualitas air', 'Uji safety valve', 'Cek burner & nyala api', 'Catat tekanan kerja']],
            ['PM-CHILL', 'Servis chiller kitchen (3 bulanan)', 'category', null, 'CHILL', 90, null, null, $today->copy()->subDays(2), 'P4',
                ['Bersihkan kondensor', 'Cek tekanan refrigeran', 'Cek seal pintu', 'Catat suhu']],
            ['INS-APAR', 'Inspeksi APAR (6 bulanan)', 'category', null, 'APAR', 180, null, null, $today->copy()->addDays(40), 'P4',
                ['Cek manometer', 'Cek pin & segel', 'Cek selang & nozzle', 'Perbarui kartu inspeksi']],
        ];
        foreach ($plans as [$code, $name, $scope, $legacy, $cat, $days, $meter, $meterInt, $start, $prio, $list]) {
            $plan = \App\Models\PmPlan::create([
                'property_id' => $p->id, 'code' => $code, 'name' => $name, 'scope' => $scope,
                'asset_id' => $legacy ? $asset($legacy)?->id : null,
                'category_id' => $cat ? AssetCategory::withoutGlobalScopes()->where('property_id', $p->id)->where('code', $cat)->value('id') : null,
                'wo_type' => str_starts_with($code, 'INS') ? 'inspection' : 'preventive', 'interval_days' => $days,
                'meter_parameter_id' => $meter ? $param($meter)?->id : null, 'interval_meter' => $meterInt,
                'starts_on' => $start->toDateString(), 'lead_days' => 7, 'priority' => $prio, 'pic_id' => $tech->id, 'checklist' => $list, 'is_active' => true,
            ]);
            $pm->syncSchedules($plan);
        }
        // pembacaan sekarang: jam genset lewat 250 jam sejak servis → WO PM; suhu chiller kritis → WO predictive
        if ($gen && $param('JAM-OPS')) {
            $pm->recordReading($gen, $param('JAM-OPS'), 1442, $ce, 'hour meter');
        }
        if ($chill && $param('SUHU-CHILL')) {
            $pm->recordReading($chill, $param('SUHU-CHILL'), 4.2, $tech, null, now()->subDay());
            $pm->recordReading($chill, $param('SUHU-CHILL'), 9.1, $tech, 'pintu sudah ditutup rapat');
        }
        $pm->generateDue();
    }

    /** Contoh data keuangan & register dokumen (hanya bila belum ada) agar fitur penyusutan dan dokumen dapat dicoba. */
    protected function fixedAssetDemo(Property $p, User $ce): void
    {
        if (\App\Models\AssetDocument::withoutGlobalScopes()->where('property_id', $p->id)->exists()) {
            return;
        }
        $asset = fn (string $legacy) => Asset::withoutGlobalScopes()->where('property_id', $p->id)->where('legacy_code', $legacy)->first();
        $fin = [
            // legacy, biaya, tanggal perolehan, saldo awal migrasi (akum. komersial, fiskal) per 31-12-2025
            ['LEG-GEN-01', 850000000, '2019-03-15', [387222222, 363020833]],
            ['LEG-BOI-01', 420000000, '2018-07-01', [210000000, 196875000]],
            ['LEG-PNL-01', 310000000, '2017-01-10', null],
            ['LEG-CHL-01', 95000000, '2024-02-05', null],
            ['LEG-FRZ-01', 38000000, '2025-08-20', null],
            ['LEG-PRJ-01', 42000000, '2023-05-02', null],
            ['LEG-SND-01', 165000000, '2023-05-02', null],
            ['LEG-APR-01', 1850000, '2025-01-15', null],
            ['LEG-CTV-01', 4200000, '2024-11-01', null],
        ];
        foreach ($fin as [$legacy, $cost, $date, $opening]) {
            if (! $a = $asset($legacy)) {
                continue;
            }
            $a->financial()->updateOrCreate([], [
                'acquisition_cost' => $cost, 'acquisition_date' => $date,
                'capitalisation' => $cost < 5000000 ? 'group' : 'capitalised',
                'group_reference' => $cost < 5000000 ? 'PO-SEC-2025-01' : null,
                'opening_balance_date' => $opening ? '2025-12-31' : null,
                'opening_accum_commercial' => $opening[0] ?? null,
                'opening_accum_fiscal' => $opening[1] ?? null,
            ]);
        }
        $type = fn (string $code) => \App\Models\DocumentType::where('code', $code)->value('id');
        $svc = app(\App\Services\DocumentService::class);
        $today = now(\App\Support\Fmt::tz())->startOfDay();
        $docs = [
            ['ASR-01', 'Polis Property All Risk 2026', 'Asuransi contoh', $today->copy()->subMonths(10), $today->copy()->addDays(55), ['LEG-GEN-01', 'LEG-BOI-01', 'LEG-PNL-01', 'LEG-CHL-01'], 'terbatas', ['premium' => 185000000, 'sum_insured' => 120000000000]],
            ['K3-02', 'Riksa uji genset utama', 'Disnaker Provinsi Jawa Barat', $today->copy()->subYear(), $today->copy()->addDays(25), ['LEG-GEN-01'], 'internal', []],
            ['K3-03', 'Pengesahan & riksa uji boiler', 'Disnaker Provinsi Jawa Barat', $today->copy()->subYear()->subDays(10), $today->copy()->subDays(10), ['LEG-BOI-01'], 'internal', []],
            ['K3-06', 'Kartu inspeksi APAR lobby', 'Vendor proteksi kebakaran', $today->copy()->subMonths(3), $today->copy()->addMonths(9), ['LEG-APR-01'], 'internal', []],
            ['AST-03', 'BAST chiller kitchen 1', null, \Carbon\Carbon::parse('2024-02-05'), null, ['LEG-CHL-01'], 'internal', []],
            ['KTR-SVC', 'Kontrak servis chiller & AC', 'Vendor HVAC', $today->copy()->subMonths(11), $today->copy()->addDays(80), ['LEG-CHL-01'], 'terbatas', ['notice_days' => 60, 'contract_value' => 96000000]],
        ];
        foreach ($docs as [$code, $title, $issuer, $issued, $until, $legacies, $class, $extra]) {
            if (! $typeId = $type($code)) {
                continue;
            }
            $svc->create(array_merge([
                'property_id' => $p->id, 'document_type_id' => $typeId, 'title' => $title, 'issuer' => $issuer,
                'issued_at' => $issued->toDateString(), 'valid_until' => $until?->toDateString(), 'no_expiry' => $until === null,
                'pic_id' => $ce->id, 'classification' => $class, 'original_location' => 'brankas',
                'asset_ids' => collect($legacies)->map(fn ($l) => $asset($l)?->id)->filter()->all(),
            ], $extra), null);
        }
    }
}
