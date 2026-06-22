<?php

namespace Database\Seeders;

use App\Constants\BuyerClassification;
use App\Constants\DayOfWeek;
use App\Constants\DriverStatus;
use App\Constants\UserRole;
use App\Domain\Deliveries\DriverRegionPlanService;
use App\Models\Company;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get companies
        $suppliers = Company::where('type', 'supplier')->get();
        $buyers = Company::where('type', 'buyer')->get();

        // Create Admin User
        User::updateOrCreate(
            ['email' => 'admin@medmarket.com'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password123'),
                'company_id' => null,
                'role' => UserRole::ADMIN,
            ]
        );

        if ($suppliers->isNotEmpty()) {
            $supplier1 = $suppliers->first();
            $supplier2 = $suppliers->skip(1)->first() ?? $supplier1;

            User::updateOrCreate(
                ['email' => 'ahmed@medsupply.com'],
                [
                    'name' => 'أحمد محمد العلي',
                    'password' => Hash::make('password123'),
                    'company_id' => $supplier1->id,
                    'role' => UserRole::SUPPLIER,
                ]
            );

            User::updateOrCreate(
                ['email' => 'fatima@pharmadist.com'],
                [
                    'name' => 'فاطمة علي السالم',
                    'password' => Hash::make('password123'),
                    'company_id' => $supplier2->id,
                    'role' => UserRole::SUPPLIER,
                ]
            );

            $supplier3 = $suppliers->skip(2)->first();
            if ($supplier3) {
                User::updateOrCreate(
                    ['email' => 'omar@globalmed.com'],
                    [
                        'name' => 'عمر خالد الغامدي',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier3->id,
                        'role' => UserRole::SUPPLIER,
                    ]
                );
            }

            $supplier4 = $suppliers->skip(3)->first();
            if ($supplier4) {
                User::updateOrCreate(
                    ['email' => 'nora@healthworld.com'],
                    [
                        'name' => 'نورة عبدالرحمن السعيد',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier4->id,
                        'role' => UserRole::SUPPLIER,
                    ]
                );
            }
        }

        // Create Buyer Users (Pharmacists)
        if ($buyers->isNotEmpty()) {
            $buyer1 = $buyers->first();
            User::updateOrCreate(
                ['email' => 'khaled@citypharmacy.com'],
                [
                    'name' => 'خالد عبدالله النجار',
                    'password' => Hash::make('password123'),
                    'company_id' => $buyer1->id,
                    'role' => UserRole::BUYER,
                ]
            );

            if ($buyers->count() > 1) {
                $buyer2 = $buyers->skip(1)->first();
                User::updateOrCreate(
                    ['email' => 'sara@healthcarepharm.com'],
                    [
                        'name' => 'سارة حسن المالكي',
                        'password' => Hash::make('password123'),
                        'company_id' => $buyer2->id,
                        'role' => UserRole::BUYER,
                    ]
                );
            }

            if ($buyers->count() > 2) {
                $buyer3 = $buyers->skip(2)->first();
                User::updateOrCreate(
                    ['email' => 'mohammed@communitypharm.com'],
                    [
                        'name' => 'محمد إبراهيم الحربي',
                        'password' => Hash::make('password123'),
                        'company_id' => $buyer3->id,
                        'role' => UserRole::BUYER,
                    ]
                );
            }
        }

        // Create Driver Users (belonging to suppliers) with full work/employment profiles.
        $drivers = [];
        if ($suppliers->isNotEmpty()) {
            $driverDefinitions = [
                [
                    'email' => 'driver1@medsupply.com',
                    'name' => 'عبدالرحمن صالح الدوسري',
                    'phone' => '+966501234567',
                    'company' => $suppliers->first(),
                    'work_start_time' => '08:00',
                    'work_end_time' => '17:00',
                    'driver_status' => DriverStatus::AVAILABLE,
                ],
                [
                    'email' => 'driver2@pharmadist.com',
                    'name' => 'سعد محمد القحطاني',
                    'phone' => '+966502345678',
                    'company' => $suppliers->skip(1)->first(),
                    'work_start_time' => '09:00',
                    'work_end_time' => '18:00',
                    'driver_status' => DriverStatus::ON_DELIVERY,
                ],
                [
                    'email' => 'driver3@healthsupply.com',
                    'name' => 'يوسف أحمد العتيبي',
                    'phone' => '+966503456789',
                    'company' => $suppliers->skip(2)->first(),
                    'work_start_time' => '07:30',
                    'work_end_time' => '15:30',
                    'driver_status' => DriverStatus::AVAILABLE,
                ],
                [
                    'email' => 'driver4@healthworld.com',
                    'name' => 'محمد سعيد الشمري',
                    'phone' => '+966504567890',
                    'company' => $suppliers->skip(3)->first(),
                    'work_start_time' => '10:00',
                    'work_end_time' => '19:00',
                    'driver_status' => DriverStatus::OFF,
                ],
            ];

            foreach ($driverDefinitions as $definition) {
                if (! $definition['company']) {
                    continue;
                }

                $drivers[$definition['email']] = User::updateOrCreate(
                    ['email' => $definition['email']],
                    [
                        'name' => $definition['name'],
                        'phone' => $definition['phone'],
                        'password' => Hash::make('password123'),
                        'company_id' => $definition['company']->id,
                        'role' => UserRole::DRIVER,
                        'work_start_time' => $definition['work_start_time'],
                        'work_end_time' => $definition['work_end_time'],
                        'employment_start_date' => now()->subMonths(8)->toDateString(),
                        'employment_end_date' => null,
                        'driver_status' => $definition['driver_status'],
                    ]
                );
            }

            $this->seedDriverRegionPlans($drivers);
        }

        // Create Supplier-Associated Salesman Users (work for supplier companies, like drivers)
        if ($suppliers->isNotEmpty()) {
            $supplier2 = $suppliers->skip(1)->first() ?? $suppliers->first();
            if ($supplier2) {
                User::updateOrCreate(
                    ['email' => 'salesman1@pharmadist.com'],
                    [
                        'name' => 'ياسر عبدالله الحمدان',
                        'phone' => '+966505678901',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier2->id,
                        'role' => UserRole::SALESMAN,
                    ]
                );
            }

            $supplier3 = $suppliers->skip(2)->first();
            if ($supplier3) {
                User::updateOrCreate(
                    ['email' => 'salesman2@globalmed.com'],
                    [
                        'name' => 'فيصل سعود المطيري',
                        'phone' => '+966506789012',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier3->id,
                        'role' => UserRole::SALESMAN,
                    ]
                );
            }
        }

        // Create Independent Salesman User (has own buyer/salesman company)
        $salesmanCompany = Company::where('buyer_classification', BuyerClassification::SALESMAN)->first();
        if ($salesmanCompany) {
            User::updateOrCreate(
                ['email' => 'rami@alamaldist.com'],
                [
                    'name' => 'رامي حسن الزهراني',
                    'phone' => '+966507890123',
                    'password' => Hash::make('password123'),
                    'company_id' => $salesmanCompany->id,
                    'role' => UserRole::SALESMAN,
                ]
            );
        }

        $this->command->info('تم إنشاء المستخدمين بنجاح (مدراء، موردين، صيادلة، سائقين، مندوبين).');
    }

    /**
     * Assign region plans (fixed + daily) and holidays to the seeded drivers so the
     * driver region-plan feature has realistic sample data.
     *
     * @param  array<string, User>  $drivers
     */
    private function seedDriverRegionPlans(array $drivers): void
    {
        $regions = Region::query()->orderBy('id')->get()->values();

        if ($regions->count() < 6) {
            $this->command->warn('عدد المناطق غير كافٍ لإنشاء خطط مناطق السائقين.');

            return;
        }

        $planService = app(DriverRegionPlanService::class);

        // Driver 1 — fixed plan covering two regions every day.
        if (isset($drivers['driver1@medsupply.com'])) {
            $planService->setHolidays($drivers['driver1@medsupply.com'], []);
            $planService->setFixedPlan(
                $drivers['driver1@medsupply.com'],
                [$regions[0]->id, $regions[1]->id]
            );
        }

        // Driver 2 — daily plan, Saturday through Thursday, Friday off.
        if (isset($drivers['driver2@pharmadist.com'])) {
            $planService->setHolidays($drivers['driver2@pharmadist.com'], [DayOfWeek::FRIDAY]);
            $planService->setDailyPlan($drivers['driver2@pharmadist.com'], [
                DayOfWeek::SATURDAY => $regions[0]->id,
                DayOfWeek::SUNDAY => $regions[1]->id,
                DayOfWeek::MONDAY => $regions[2]->id,
                DayOfWeek::TUESDAY => $regions[3]->id,
                DayOfWeek::WEDNESDAY => $regions[4]->id,
                DayOfWeek::THURSDAY => $regions[5]->id,
            ]);
        }

        // Driver 3 — fixed plan covering a single region.
        if (isset($drivers['driver3@healthsupply.com'])) {
            $planService->setHolidays($drivers['driver3@healthsupply.com'], []);
            $planService->setFixedPlan(
                $drivers['driver3@healthsupply.com'],
                [$regions[2]->id]
            );
        }

        // Driver 4 — daily plan, Sunday through Thursday, Friday and Saturday off.
        if (isset($drivers['driver4@healthworld.com'])) {
            $planService->setHolidays($drivers['driver4@healthworld.com'], [DayOfWeek::FRIDAY, DayOfWeek::SATURDAY]);
            $planService->setDailyPlan($drivers['driver4@healthworld.com'], [
                DayOfWeek::SUNDAY => $regions[3]->id,
                DayOfWeek::MONDAY => $regions[4]->id,
                DayOfWeek::TUESDAY => $regions[5]->id,
                DayOfWeek::WEDNESDAY => $regions[0]->id,
                DayOfWeek::THURSDAY => $regions[1]->id,
            ]);
        }

        $this->command->info('تم إنشاء خطط مناطق السائقين (ثابتة ويومية) مع أيام العطل.');
    }
}
