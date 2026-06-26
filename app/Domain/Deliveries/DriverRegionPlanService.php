<?php

namespace App\Domain\Deliveries;

use App\Constants\DayOfWeek;
use App\Constants\RegionPlanType;
use App\Exceptions\DomainException;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DriverRegionPlanService
{
    /**
     * Assign a fixed region plan: the driver covers the same set of regions every day.
     * Replaces any existing region assignments (fixed or daily).
     *
     * @param  list<int>  $regionIds
     */
    public function setFixedPlan(User $driver, array $regionIds): User
    {
        return DB::transaction(function () use ($driver, $regionIds): User {
            $driver->driverRegions()->detach();

            $attachments = [];
            foreach (array_unique($regionIds) as $regionId) {
                $attachments[$regionId] = ['day_of_week' => null];
            }
            $driver->driverRegions()->attach($attachments);

            $driver->forceFill(['region_plan_type' => RegionPlanType::FIXED])->save();

            $driver->refresh();

            return $driver;
        });
    }

    /**
     * Assign a daily region plan: one region per weekday (a weekly schedule).
     * Replaces any existing region assignments. Weekdays marked as holidays cannot
     * be assigned a region.
     *
     * @param  array<string, int>  $schedule  Map of weekday (DayOfWeek) to region id.
     *
     * @throws DomainException When a weekday is invalid or is a holiday.
     */
    public function setDailyPlan(User $driver, array $schedule): User
    {
        foreach (array_keys($schedule) as $weekday) {
            if (! DayOfWeek::isValid($weekday)) {
                throw new DomainException("Invalid weekday: {$weekday}");
            }

            if ($driver->isHoliday($weekday)) {
                throw new DomainException("Cannot assign a region to a holiday ({$weekday}).");
            }
        }

        return DB::transaction(function () use ($driver, $schedule): User {
            $driver->driverRegions()->detach();

            foreach ($schedule as $weekday => $regionId) {
                $driver->driverRegions()->attach($regionId, ['day_of_week' => $weekday]);
            }

            $driver->forceFill(['region_plan_type' => RegionPlanType::DAILY])->save();

            $driver->refresh();

            return $driver;
        });
    }

    /**
     * Set the driver's holiday weekdays. Any daily-plan region assigned to a newly
     * added holiday weekday is automatically removed, since no region may be served
     * on a holiday.
     *
     * @param  list<string>  $weekdays
     *
     * @throws DomainException When a weekday is invalid.
     */
    public function setHolidays(User $driver, array $weekdays): User
    {
        $weekdays = array_values(array_unique($weekdays));

        foreach ($weekdays as $weekday) {
            if (! DayOfWeek::isValid($weekday)) {
                throw new DomainException("Invalid weekday: {$weekday}");
            }
        }

        return DB::transaction(function () use ($driver, $weekdays): User {
            if (! empty($weekdays)) {
                $driver->driverRegions()
                    ->wherePivotIn('day_of_week', $weekdays)
                    ->detach();
            }

            $driver->forceFill(['holidays' => $weekdays])->save();

            $driver->refresh();

            return $driver;
        });
    }

    /**
     * Build the structured region plan for a driver.
     *
     * @return array{
     *     planType: string,
     *     holidays: list<string>,
     *     regions: array<int, \Illuminate\Database\Eloquent\Model>,
     *     schedule: array<string, \Illuminate\Database\Eloquent\Model|null>
     * }
     */
    public function getPlan(User $driver): array
    {
        $planType = $driver->region_plan_type ?? RegionPlanType::FIXED;

        // Fetch the assigned regions and read each row's pivot weekday, then partition
        // into fixed (null weekday) vs per-weekday assignments.
        $regions = $driver->driverRegions()->get();
        $regions->load('city');

        $assignments = $regions->map(function (Region $region) {
            $pivot = $region->getRelationValue('pivot');

            return [
                'day' => $pivot->day_of_week ?? null,
                'region' => $region,
            ];
        });

        $fixedRegions = $assignments
            ->whereNull('day')
            ->map(fn ($assignment) => $assignment['region'])
            ->values()
            ->all();

        $schedule = [];
        foreach (DayOfWeek::all() as $weekday) {
            $match = $assignments->firstWhere('day', $weekday);
            $schedule[$weekday] = $match['region'] ?? null;
        }

        return [
            'planType' => $planType,
            'holidays' => $driver->holidayWeekdays(),
            'regions' => $fixedRegions,
            'schedule' => $schedule,
        ];
    }
}
