<?php

namespace App\Support;

use App\Models\PatientSchedule;
use Carbon\Carbon;

class VisitStatus
{
    public const COMPLETE = 'complete';

    public const IN_PROGRESS = 'in_progress';

    public const UPCOMING = 'upcoming';

    public const MISSED = 'missed';

    /**
     * Classify a visit using the same rules as missed-visit alerts and the schedules board.
     *
     * - Completed / missed: explicit status on the record
     * - Missed: shift window ended without completion (matches MISSED VISIT alerts)
     * - In progress: carer has checked in and the shift has not ended
     * - Upcoming: future shifts and in-window shifts not yet checked in (DUE NOW)
     */
    public static function classify(PatientSchedule $schedule, ?Carbon $now = null): string
    {
        $now ??= now();
        $status = strtolower(trim((string) ($schedule->status ?? '')));

        if ($status === 'completed') {
            return self::COMPLETE;
        }

        if ($status === 'missed') {
            return self::MISSED;
        }

        if ($schedule->end_at && $schedule->end_at->lt($now)) {
            return self::MISSED;
        }

        if ($schedule->checked_in_at !== null) {
            return self::IN_PROGRESS;
        }

        return self::UPCOMING;
    }

    public static function displayLabel(string $status): string
    {
        return match ($status) {
            self::COMPLETE => 'Completed',
            self::IN_PROGRESS => 'In Progress',
            self::UPCOMING => 'Upcoming',
            self::MISSED => 'Missed',
            default => 'Unknown',
        };
    }

    /**
     * @param  iterable<int, PatientSchedule>  $schedules
     * @return array{
     *   total: int,
     *   complete: int,
     *   in_progress: int,
     *   upcoming: int,
     *   missed: int,
     *   completed: int,
     *   overdue: int
     * }
     */
    public static function summarize(iterable $schedules, ?Carbon $now = null): array
    {
        $now ??= now();
        $counts = [
            self::COMPLETE => 0,
            self::IN_PROGRESS => 0,
            self::UPCOMING => 0,
            self::MISSED => 0,
        ];

        foreach ($schedules as $schedule) {
            $counts[self::classify($schedule, $now)]++;
        }

        $total = array_sum($counts);

        return [
            'total' => $total,
            'complete' => $counts[self::COMPLETE],
            'in_progress' => $counts[self::IN_PROGRESS],
            'upcoming' => $counts[self::UPCOMING],
            'missed' => $counts[self::MISSED],
            'completed' => $counts[self::COMPLETE],
            'overdue' => 0,
        ];
    }
}
