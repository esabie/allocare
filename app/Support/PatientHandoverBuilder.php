<?php

namespace App\Support;

use App\Models\CareJournalEntry;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientBowelRecord;
use App\Models\PatientCarePlanSummary;
use App\Models\PatientFluidRecord;
use App\Models\PatientHandover;
use App\Models\PatientIncident;
use App\Models\PatientRiskAssessment;
use App\Models\PatientSchedule;
use App\Models\PatientVital;
use App\Models\PatientWoundAssessment;
use App\Models\ScheduleVisitTask;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PatientHandoverBuilder
{
  /**
   * @return array{
   *   periodStart: string,
   *   periodEnd: string,
   *   periodStartLabel: string,
   *   periodEndLabel: string,
   *   sections: array<string, array<int, array<string, mixed>>>,
   *   timeline: array<int, array<string, mixed>>,
   *   suggestedFields: array<string, string|null>
   * }
   */
  public static function build(
    Patient $patient,
    string $shiftType,
    string $shiftDate,
    ?PatientSchedule $schedule = null,
  ): array {
    [$periodStart, $periodEnd] = self::resolveShiftWindow($patient, $shiftType, $shiftDate, $schedule);

    $sections = [
      'careNotes' => self::collectCareNotes($patient, $periodStart, $periodEnd),
      'observations' => self::collectObservations($patient, $periodStart, $periodEnd),
      'incidents' => self::collectIncidents($patient, $periodStart, $periodEnd, null),
      'medications' => self::collectMedications($patient, $periodStart, $periodEnd),
      'safeguarding' => self::collectIncidents($patient, $periodStart, $periodEnd, 'safeguarding-concern'),
      'behaviourIncidents' => self::collectIncidents($patient, $periodStart, $periodEnd, 'behaviour-incident'),
      'outstandingTasks' => self::collectOutstandingTasks($patient, $periodStart, $periodEnd),
      'overdueReviews' => self::collectOverdueReviews($patient),
    ];

    $timeline = self::buildTimeline($sections, $periodStart, $periodEnd);
    $suggestedFields = self::suggestHandoverFields($shiftType, $sections, $timeline);

    return [
      'periodStart' => $periodStart->toIso8601String(),
      'periodEnd' => $periodEnd->toIso8601String(),
      'periodStartLabel' => $periodStart->format('d M Y, H:i'),
      'periodEndLabel' => $periodEnd->format('d M Y, H:i'),
      'sections' => $sections,
      'timeline' => $timeline,
      'suggestedFields' => $suggestedFields,
    ];
  }

  /** @return array{0: Carbon, 1: Carbon} */
  private static function resolveShiftWindow(
    Patient $patient,
    string $shiftType,
    string $shiftDate,
    ?PatientSchedule $schedule,
  ): array {
    $shiftDay = Carbon::parse($shiftDate)->startOfDay();

    if ($schedule !== null) {
      $periodStart = $schedule->checked_in_at ?? $schedule->start_at ?? $shiftDay->copy()->setTime(7, 0);
      $periodEnd = $schedule->checked_out_at ?? now();
      if ($schedule->end_at && $schedule->end_at->lt($periodEnd)) {
        $periodEnd = $schedule->end_at->copy();
      }
    } elseif ($shiftType === PatientHandover::SHIFT_DAY) {
      $periodStart = $shiftDay->copy()->setTime(7, 0);
      $periodEnd = $shiftDay->copy()->setTime(19, 0);
      if ($periodEnd->isFuture()) {
        $periodEnd = now();
      }
    } else {
      $periodStart = $shiftDay->copy()->setTime(19, 0);
      $periodEnd = $shiftDay->copy()->addDay()->setTime(7, 0);
      if ($periodEnd->isFuture()) {
        $periodEnd = now();
      }
    }

    $lastHandover = PatientHandover::query()
      ->where('patient_id', $patient->id)
      ->orderByDesc('recorded_at')
      ->first();

    if ($lastHandover?->recorded_at && $lastHandover->recorded_at->gt($periodStart)) {
      $periodStart = $lastHandover->recorded_at->copy();
    }

    if ($periodEnd->lte($periodStart)) {
      $periodEnd = now();
    }

    return [$periodStart, $periodEnd];
  }

  /** @return array<int, array<string, mixed>> */
  private static function collectCareNotes(Patient $patient, Carbon $from, Carbon $to): array
  {
    return CareJournalEntry::query()
      ->where('patient_id', $patient->id)
      ->whereBetween('recorded_at', [$from, $to])
      ->orderBy('recorded_at')
      ->get()
      ->map(fn (CareJournalEntry $entry) => [
        'at' => $entry->recorded_at?->toIso8601String(),
        'atLabel' => $entry->recorded_at?->format('d M Y, H:i'),
        'summary' => Str::limit(trim($entry->body), 240),
        'author' => $entry->author?->name,
      ])
      ->values()
      ->all();
  }

  /** @return array<int, array<string, mixed>> */
  private static function collectObservations(Patient $patient, Carbon $from, Carbon $to): array
  {
    $items = [];

    foreach (PatientVital::query()
      ->where('patient_id', $patient->id)
      ->whereBetween('recorded_at', [$from, $to])
      ->orderBy('recorded_at')
      ->get() as $vital) {
      $items[] = [
        'at' => $vital->recorded_at?->toIso8601String(),
        'atLabel' => $vital->recorded_at?->format('d M Y, H:i'),
        'type' => 'vitals',
        'summary' => self::formatVitalSummary($vital),
      ];
    }

    foreach (PatientFluidRecord::query()
      ->where('patient_id', $patient->id)
      ->whereBetween('recorded_at', [$from, $to])
      ->orderBy('recorded_at')
      ->get() as $record) {
      $items[] = [
        'at' => $record->recorded_at?->toIso8601String(),
        'atLabel' => $record->recorded_at?->format('d M Y, H:i'),
        'type' => 'fluid',
        'summary' => sprintf(
          'Fluid balance — intake %d ml, output %d ml',
          (int) ($record->fluid_intake_ml ?? 0),
          (int) ($record->fluid_output_ml ?? 0),
        ),
      ];
    }

    foreach (PatientBowelRecord::query()
      ->where('patient_id', $patient->id)
      ->whereBetween('recorded_at', [$from, $to])
      ->orderBy('recorded_at')
      ->get() as $record) {
      $items[] = [
        'at' => $record->recorded_at?->toIso8601String(),
        'atLabel' => $record->recorded_at?->format('d M Y, H:i'),
        'type' => 'bowel',
        'summary' => 'Bowel record — type: '.($record->bowel_type ?? 'not recorded'),
      ];
    }

    usort($items, fn (array $a, array $b) => strcmp((string) $a['at'], (string) $b['at']));

    return $items;
  }

  /** @return array<int, array<string, mixed>> */
  private static function collectIncidents(
    Patient $patient,
    Carbon $from,
    Carbon $to,
    ?string $categorySlug,
  ): array {
    return PatientIncident::query()
      ->where('patient_id', $patient->id)
      ->when($categorySlug !== null, fn ($q) => $q->where('incident_category', $categorySlug))
      ->where(function ($query) use ($from, $to) {
        $query->whereBetween('submitted_at', [$from, $to])
          ->orWhere(function ($nested) use ($from, $to) {
            $nested->whereNull('submitted_at')
              ->whereDate('incident_date', '>=', $from->toDateString())
              ->whereDate('incident_date', '<=', $to->toDateString());
          });
      })
      ->orderBy('submitted_at')
      ->orderBy('incident_date')
      ->get()
      ->map(fn (PatientIncident $incident) => [
        'at' => ($incident->submitted_at ?? $incident->incident_date)?->toIso8601String(),
        'atLabel' => ($incident->submitted_at ?? $incident->incident_date)?->format('d M Y, H:i'),
        'category' => $incident->incident_category,
        'title' => $incident->incident_title,
        'severity' => $incident->severity,
        'summary' => trim($incident->incident_title.' — '.($incident->severity ?? 'unspecified severity')),
      ])
      ->values()
      ->all();
  }

  /** @return array<int, array<string, mixed>> */
  private static function collectMedications(Patient $patient, Carbon $from, Carbon $to): array
  {
    return MedicationAdministration::query()
      ->where('patient_id', $patient->id)
      ->whereNull('voided_at')
      ->where(function ($query) use ($from, $to) {
        $query->whereBetween('administered_at', [$from, $to])
          ->orWhereBetween('scheduled_for', [$from, $to]);
      })
      ->with('medication:id,name,dose')
      ->orderBy('administered_at')
      ->orderBy('scheduled_for')
      ->get()
      ->map(function (MedicationAdministration $admin) {
        $medName = $admin->medication?->name ?? 'Medication';
        $dose = $admin->medication?->dose;
        $label = $dose ? "{$medName} ({$dose})" : $medName;
        $at = $admin->administered_at ?? $admin->scheduled_for;

        return [
          'at' => $at?->toIso8601String(),
          'atLabel' => $at?->format('d M Y, H:i'),
          'status' => $admin->status,
          'summary' => "{$label} — ".strtoupper((string) $admin->status)
            .($admin->notes ? ': '.Str::limit($admin->notes, 120) : ''),
          'requiresFollowUp' => in_array($admin->status, ['refused', 'missed', 'withheld'], true),
        ];
      })
      ->values()
      ->all();
  }

  /** @return array<int, array<string, mixed>> */
  private static function collectOutstandingTasks(Patient $patient, Carbon $from, Carbon $to): array
  {
    $scheduleIds = PatientSchedule::query()
      ->where('patient_id', $patient->id)
      ->where(function ($query) use ($from, $to) {
        $query->whereBetween('start_at', [$from, $to])
          ->orWhereBetween('end_at', [$from, $to]);
      })
      ->pluck('id');

    if ($scheduleIds->isEmpty()) {
      return [];
    }

    return ScheduleVisitTask::query()
      ->whereIn('patient_schedule_id', $scheduleIds)
      ->where(function ($query) {
        $query->whereNull('outcome')
          ->orWhereIn('outcome', ['refused', 'unable', 'escalated']);
      })
      ->with('schedule:id,start_at,end_at')
      ->orderBy('sort_order')
      ->get()
      ->map(fn (ScheduleVisitTask $task) => [
        'at' => $task->schedule?->start_at?->toIso8601String(),
        'atLabel' => $task->schedule?->start_at?->format('d M Y, H:i'),
        'taskLabel' => $task->task_label,
        'outcome' => $task->outcome ?? 'pending',
        'notes' => $task->notes,
        'summary' => $task->task_label.' — '.($task->outcome ?? 'not completed')
          .($task->notes ? ' ('.Str::limit($task->notes, 80).')' : ''),
        'requiresFollowUp' => true,
      ])
      ->values()
      ->all();
  }

  /** @return array<int, array<string, mixed>> */
  private static function collectOverdueReviews(Patient $patient): array
  {
    $items = [];

    foreach (PatientRiskAssessment::query()
      ->where('patient_id', $patient->id)
      ->where('status', 'active')
      ->whereNotNull('next_review_due_at')
      ->whereDate('next_review_due_at', '<', now()->toDateString())
      ->orderBy('next_review_due_at')
      ->get() as $assessment) {
      $items[] = [
        'at' => $assessment->next_review_due_at?->toIso8601String(),
        'atLabel' => $assessment->next_review_due_at?->format('d M Y'),
        'type' => 'risk_assessment',
        'summary' => 'Overdue risk assessment review — '.$assessment->risk_slug
          .' (due '.$assessment->next_review_due_at?->format('d M Y').')',
        'requiresFollowUp' => true,
      ];
    }

    foreach (PatientCarePlanSummary::query()
      ->where('patient_slug', $patient->url_key)
      ->whereNotNull('review_due_at')
      ->whereDate('review_due_at', '<', now()->toDateString())
      ->orderBy('review_due_at')
      ->get() as $plan) {
      $items[] = [
        'at' => $plan->review_due_at?->toIso8601String(),
        'atLabel' => $plan->review_due_at?->format('d M Y'),
        'type' => 'care_plan',
        'summary' => 'Overdue care plan review — '.Str::of($plan->plan_slug)->replace('-', ' ')->title()
          .' (due '.$plan->review_due_at?->format('d M Y').')',
        'requiresFollowUp' => true,
      ];
    }

    if (class_exists(PatientWoundAssessment::class)) {
      foreach (PatientWoundAssessment::query()
        ->where('patient_id', $patient->id)
        ->whereNotNull('review_due_at')
        ->whereDate('review_due_at', '<=', now()->toDateString())
        ->orderBy('review_due_at')
        ->get() as $wound) {
        $items[] = [
          'at' => $wound->review_due_at?->toIso8601String(),
          'atLabel' => $wound->review_due_at?->format('d M Y'),
          'type' => 'wound_review',
          'summary' => 'Overdue wound review — '.$wound->wound_site
            .' (due '.$wound->review_due_at?->format('d M Y').')',
          'requiresFollowUp' => true,
        ];
      }
    }

    usort($items, fn (array $a, array $b) => strcmp((string) $a['at'], (string) $b['at']));

    return $items;
  }

  /**
   * @param  array<string, array<int, array<string, mixed>>>  $sections
   * @return array<int, array<string, mixed>>
   */
  private static function buildTimeline(array $sections, Carbon $from, Carbon $to): array
  {
    $timeline = [];

    $push = function (string $category, array $item, bool $followUp = false) use (&$timeline): void {
      if (empty($item['at'])) {
        return;
      }
      $timeline[] = [
        'at' => $item['at'],
        'atLabel' => $item['atLabel'] ?? '',
        'category' => $category,
        'summary' => $item['summary'] ?? '',
        'requiresFollowUp' => $followUp || ($item['requiresFollowUp'] ?? false),
      ];
    };

    foreach ($sections['careNotes'] as $item) {
      $push('Care note', $item);
    }
    foreach ($sections['observations'] as $item) {
      $push('Observation', $item);
    }
    foreach ($sections['incidents'] as $item) {
      $push('Incident', $item, true);
    }
    foreach ($sections['safeguarding'] as $item) {
      $push('Safeguarding', $item, true);
    }
    foreach ($sections['behaviourIncidents'] as $item) {
      $push('Behaviour', $item, true);
    }
    foreach ($sections['medications'] as $item) {
      $push('Medication', $item, (bool) ($item['requiresFollowUp'] ?? false));
    }
    foreach ($sections['outstandingTasks'] as $item) {
      $push('Outstanding task', $item, true);
    }
    foreach ($sections['overdueReviews'] as $item) {
      $push('Overdue review', $item, true);
    }

    usort($timeline, fn (array $a, array $b) => strcmp((string) $a['at'], (string) $b['at']));

    return array_values($timeline);
  }

  /**
   * @param  array<string, array<int, array<string, mixed>>>  $sections
   * @param  array<int, array<string, mixed>>  $timeline
   * @return array<string, string|null>
   */
  private static function suggestHandoverFields(string $shiftType, array $sections, array $timeline): array
  {
    $bullet = fn (array $items, int $limit = 6): ?string => collect($items)
      ->take($limit)
      ->pluck('summary')
      ->filter()
      ->map(fn (string $line) => '• '.$line)
      ->implode("\n") ?: null;

    $followUp = collect($timeline)
      ->filter(fn (array $item) => $item['requiresFollowUp'] ?? false)
      ->pluck('summary')
      ->map(fn (string $line) => '• '.$line)
      ->implode("\n");

    if ($shiftType === PatientHandover::SHIFT_DAY) {
      $latestObservation = collect($sections['observations'])->last();

      return [
        'presentation' => $latestObservation['summary'] ?? null,
        'care_delivered' => $bullet($sections['careNotes']),
        'medication_summary' => $bullet($sections['medications']),
        'risks_changes' => $bullet(array_merge(
          $sections['safeguarding'],
          $sections['behaviourIncidents'],
        ), 4),
        'handover_notes' => $followUp !== '' ? $followUp : $bullet($sections['incidents']),
        'sleep_summary' => null,
        'disturbances' => null,
        'night_medications' => null,
        'seizure_respiratory_events' => null,
        'morning_priorities' => null,
      ];
    }

    $sleepTasks = collect($sections['outstandingTasks'])
      ->filter(fn (array $item) => str_contains(strtolower((string) $item['taskLabel']), 'sleep'))
      ->all();

    return [
      'presentation' => null,
      'care_delivered' => null,
      'medication_summary' => null,
      'risks_changes' => null,
      'handover_notes' => null,
      'sleep_summary' => $bullet($sleepTasks) ?: $bullet($sections['careNotes'], 3),
      'disturbances' => $bullet(array_merge(
        $sections['behaviourIncidents'],
        $sections['incidents'],
      ), 5),
      'night_medications' => $bullet($sections['medications']),
      'seizure_respiratory_events' => $bullet(
        collect($sections['observations'])
          ->filter(fn (array $item) => str_contains(strtolower((string) $item['summary']), 'spo2')
            || str_contains(strtolower((string) $item['summary']), 'respiratory'))
          ->values()
          ->all(),
        3,
      ),
      'morning_priorities' => $followUp !== '' ? $followUp : $bullet($sections['overdueReviews']),
    ];
  }

  private static function formatVitalSummary(PatientVital $vital): string
  {
    $parts = [];
    if ($vital->heart_rate !== null) {
      $parts[] = 'HR '.$vital->heart_rate;
    }
    if ($vital->bp_systolic !== null && $vital->bp_diastolic !== null) {
      $parts[] = 'BP '.$vital->bp_systolic.'/'.$vital->bp_diastolic;
    }
    if ($vital->spo2 !== null) {
      $parts[] = 'SpO2 '.$vital->spo2.'%';
    }
    if ($vital->temperature_celsius !== null) {
      $parts[] = 'Temp '.$vital->temperature_celsius.'°C';
    }
    if ($vital->pain_score !== null) {
      $parts[] = 'Pain '.$vital->pain_score.'/10';
    }
    if ($vital->other_observation) {
      $parts[] = $vital->other_observation;
    }

    return $parts !== [] ? implode(', ', $parts) : 'Vitals recorded';
  }
}
