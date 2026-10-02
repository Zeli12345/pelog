<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Screenshot;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\UsageSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data contoh untuk pengembangan (local).
 * Data asli siswa/guru diimpor lewat dashboard (Excel/CSV).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $classes = ['X RPL 1', 'X RPL 2', 'X TKJ 1', 'XI RPL 1', 'XI TKJ 1', 'XII RPL 1'];

        for ($i = 1; $i <= 24; $i++) {
            Student::query()->updateOrCreate(
                ['nisn' => sprintf('00%08d', $i)],
                [
                    'name' => fake('id_ID')->name(),
                    'class' => $classes[($i - 1) % count($classes)],
                    'birth_date' => now()->subYears(16)->subDays($i * 37)->toDateString(),
                    'is_active' => true,
                ],
            );
        }

        $staff = [
            ['nip_id' => '198501152010011001', 'name' => 'I Komang Purwata, S.Pd.', 'role' => 'teacher'],
            ['nip_id' => '199002202015012002', 'name' => 'Ni Made Ariani, S.Kom.', 'role' => 'teacher'],
            ['nip_id' => '198807102012011003', 'name' => 'I Wayan Sudiarta', 'role' => 'staff'],
        ];

        foreach ($staff as $person) {
            StaffMember::query()->updateOrCreate(
                ['nip_id' => $person['nip_id']],
                [
                    'name' => $person['name'],
                    'role' => $person['role'],
                    'is_active' => true,
                ],
            );
        }

        $deviceSpecs = [
            ['label' => 'LAB-BL-01', 'hostname' => 'LAB-BL-01', 'total' => 256, 'used' => 112, 'seen' => 1, 'status' => 'available'],
            ['label' => 'LAB-BL-02', 'hostname' => 'LAB-BL-02', 'total' => 256, 'used' => 98, 'seen' => 2, 'status' => 'available'],
            ['label' => 'LAB-BL-03', 'hostname' => 'LAB-BL-03', 'total' => 512, 'used' => 210, 'seen' => 1, 'status' => 'available'],
            ['label' => 'LAB-BL-04', 'hostname' => 'LAB-BL-04', 'total' => 256, 'used' => 140, 'seen' => 3, 'status' => 'available'],
            ['label' => 'LAB-BL-05', 'hostname' => 'LAB-BL-05', 'total' => 256, 'used' => 87, 'seen' => 1, 'status' => 'available'],
            ['label' => 'LAB-BL-06', 'hostname' => 'LAB-BL-06', 'total' => 256, 'used' => 121, 'seen' => 4, 'status' => 'available'],
            ['label' => 'LAB-BL-07', 'hostname' => 'LAB-BL-07', 'total' => 256, 'used' => 150, 'seen' => 2880, 'status' => 'available'],
            ['label' => 'LAB-BL-08', 'hostname' => 'LAB-BL-08', 'total' => 256, 'used' => 60, 'seen' => 60, 'status' => 'maintenance'],
        ];

        foreach ($deviceSpecs as $spec) {
            Device::query()->updateOrCreate(
                ['hostname' => $spec['hostname']],
                [
                    'uuid' => (string) Str::uuid(),
                    'label' => $spec['label'],
                    'device_token_hash' => hash('sha256', 'demo-token-'.$spec['hostname']),
                    'device_type' => 'laptop',
                    'location_label' => 'Lab Komputer 1',
                    'status' => $spec['status'],
                    'storage_total_gb' => $spec['total'],
                    'storage_used_gb' => $spec['used'],
                    'agent_version' => '0.9.0-demo',
                    'windows_version' => 'Windows 10 Pro 22H2',
                    'last_seen_at' => now()->subMinutes($spec['seen']),
                    'enrolled_at' => now()->subDays(7),
                    'is_active' => true,
                ],
            );
        }

        $subjects = Subject::query()->pluck('id', 'code');

        $students = Student::query()->orderBy('id')->get();
        $staffMembers = StaffMember::query()->orderBy('id')->get();

        $sessionSpecs = [
            [
                'uuid' => '00000000-0000-4000-8000-000000000001',
                'hostname' => 'LAB-BL-01', 'user_type' => 'student', 'student' => $students->get(2), 'staff' => null,
                'subject' => 'PWPB', 'purpose' => 'Praktikum membuat layout responsive dengan CSS Grid & Flexbox',
                'started' => now()->subMinutes(42), 'closed' => null, 'reason' => null,
            ],
            [
                'uuid' => '00000000-0000-4000-8000-000000000002',
                'hostname' => 'LAB-BL-03', 'user_type' => 'staff', 'student' => null, 'staff' => $staffMembers->first(),
                'subject' => null, 'purpose' => 'Pemeliharaan perangkat & pembaruan software lab',
                'started' => now()->subMinutes(15), 'closed' => null, 'reason' => null,
            ],
            [
                'uuid' => '00000000-0000-4000-8000-000000000003',
                'hostname' => 'LAB-BL-04', 'user_type' => 'student', 'student' => $students->get(5), 'staff' => null,
                'subject' => 'BD', 'purpose' => 'Latihan query SQL JOIN untuk tugas basis data',
                'started' => now()->subHours(3), 'closed' => now()->subHours(2), 'reason' => 'normal',
            ],
            [
                'uuid' => '00000000-0000-4000-8000-000000000004',
                'hostname' => 'LAB-BL-05', 'user_type' => 'student', 'student' => $students->get(8), 'staff' => null,
                'subject' => 'JK', 'purpose' => 'Konfigurasi IP statis dan pengujian koneksi jaringan',
                'started' => now()->subHour(), 'closed' => now()->subMinutes(35), 'reason' => 'normal',
                'screenshot' => true,
            ],
            [
                'uuid' => '00000000-0000-4000-8000-000000000005',
                'hostname' => 'LAB-BL-06', 'user_type' => 'student', 'student' => $students->get(2), 'staff' => null,
                'subject' => 'MTK', 'purpose' => 'Mengerjakan latihan soal matematika bab matriks',
                'started' => now()->subDay()->setTime(9, 0), 'closed' => now()->subDay()->setTime(9, 45), 'reason' => 'recovery',
            ],
        ];

        foreach ($sessionSpecs as $spec) {
            $deviceModel = Device::query()->where('hostname', $spec['hostname'])->first();

            if ($deviceModel === null) {
                continue;
            }

            $session = UsageSession::query()->updateOrCreate(
                ['session_uuid' => $spec['uuid']],
                [
                    'device_id' => $deviceModel->id,
                    'user_type' => $spec['user_type'],
                    'student_id' => $spec['student']?->id,
                    'staff_id' => $spec['staff']?->id,
                    'subject_id' => $spec['subject'] ? ($subjects[$spec['subject']] ?? null) : null,
                    'usage_purpose' => $spec['purpose'],
                    'started_at_client' => $spec['started'],
                    'started_at_server' => $spec['started'],
                    'last_heartbeat_at' => $spec['closed'] ?? now(),
                    'closed_at' => $spec['closed'],
                    'close_reason' => $spec['reason'],
                    'duration_minutes' => $spec['closed'] ? (int) $spec['started']->diffInMinutes($spec['closed']) : 0,
                    'sync_source' => 'online',
                ],
            );

            if (! empty($spec['screenshot'])) {
                $this->createDemoScreenshot($session);
            }
        }
    }

    private function createDemoScreenshot(UsageSession $session): void
    {
        if ($session->screenshot()->exists() || ! extension_loaded('gd')) {
            return;
        }

        $directory = 'screenshots/demo';
        $path = $directory.'/'.$session->session_uuid.'.jpg';

        $image = imagecreatetruecolor(1280, 720);
        $background = imagecolorallocate($image, 24, 32, 44);
        imagefill($image, 0, 0, $background);

        $white = imagecolorallocate($image, 240, 240, 240);
        $accent = imagecolorallocate($image, 201, 162, 39);

        imagestring($image, 5, 40, 40, 'Demo screenshot PELOG', $white);
        imagestring($image, 4, 40, 80, 'Sesi praktikum siswa - LAB-BL-05', $accent);
        imagestring($image, 3, 40, 116, now()->timezone('Asia/Makassar')->format('d/m/Y H:i').' WITA', $white);

        for ($i = 0; $i < 5; $i++) {
            $color = imagecolorallocate($image, 40 + $i * 22, 62 + $i * 16, 92 + $i * 12);
            imagefilledrectangle($image, 40 + $i * 240, 180, 220 + $i * 240, 520, $color);
        }

        ob_start();
        imagejpeg($image, null, 80);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('local')->put($path, $binary);

        Screenshot::query()->create([
            'screenshot_uuid' => (string) Str::uuid(),
            'usage_session_id' => $session->id,
            'format' => 'jpeg',
            'path' => $path,
            'thumb_path' => null,
            'size_bytes' => strlen($binary),
            'captured_at' => $session->closed_at ?? now(),
            'captured_at_client' => $session->closed_at ?? now(),
        ]);
    }
}
