<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'        => 'Enrollment',
                'code'        => 'ENROLL',
                'description' => 'Student enrollment and registration services.',
            ],
            [
                'name'        => 'Transcript of Records',
                'code'        => 'TRANSCRIPT',
                'description' => 'Request for official transcript of records.',
            ],
            [
                'name'        => 'Certificate of Enrollment',
                'code'        => 'COE',
                'description' => 'Issuance of certificate of enrollment.',
            ],
            [
                'name'        => 'Good Moral Certificate',
                'code'        => 'GMC',
                'description' => 'Issuance of good moral character certificate.',
            ],
            [
                'name'        => 'Diploma',
                'code'        => 'DIPLOMA',
                'description' => 'Release or replacement of diploma.',
            ],
            [
                'name'        => 'Grade Inquiry',
                'code'        => 'GRADE',
                'description' => 'Inquiry or correction of grades.',
            ],
            [
                'name'        => 'Scholarship Application',
                'code'        => 'SCHOLAR',
                'description' => 'Application or renewal of scholarship grants.',
            ],
            [
                'name'        => 'Financial Assistance',
                'code'        => 'FINASST',
                'description' => 'Request for financial assistance or payment extension.',
            ],
            [
                'name'        => 'Leave of Absence',
                'code'        => 'LOA',
                'description' => 'Filing for leave of absence.',
            ],
            [
                'name'        => 'Clearance',
                'code'        => 'CLEAR',
                'description' => 'Student clearance processing.',
            ],
        ];

        Tenant::all()->each(function (Tenant $tenant) use ($types) {
            foreach ($types as $type) {
                ServiceType::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $type['code']],
                    array_merge($type, ['tenant_id' => $tenant->id, 'is_active' => true])
                );
            }
        });
    }
}
