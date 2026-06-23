<?php

namespace App\Support;

use App\Models\Patient;
use App\Models\PatientCarePlanForm;
use Illuminate\Support\Facades\Schema;

class News2Scoring
{
    public const RISK_LOW = 'low';

    public const RISK_LOW_MEDIUM = 'low_medium';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    /**
     * @param  array{
     *   respiration_rate: int,
     *   spo2: int,
     *   supplemental_oxygen: bool,
     *   bp_systolic: int,
     *   pulse: int,
     *   temperature_celsius: float,
     *   consciousness_level: string,
     *   oxygen_scale?: int
     * }  $observation
     * @return array{
     *   total_score: int,
     *   risk_level: string,
     *   risk_label: string,
     *   has_single_parameter_three: bool,
     *   flagged_parameters: array<int, string>,
     *   component_scores: array<string, int>,
     *   escalation_guidance: string,
     *   oxygen_scale: int
     * }
     */
    public static function calculate(array $observation): array
    {
        $oxygenScale = (int) ($observation['oxygen_scale'] ?? 1);
        if (! in_array($oxygenScale, [1, 2], true)) {
            $oxygenScale = 1;
        }

        $componentScores = [
            'respiration_rate' => self::scoreRespirationRate((int) $observation['respiration_rate']),
            'spo2' => self::scoreSpo2((int) $observation['spo2'], $oxygenScale),
            'supplemental_oxygen' => self::scoreSupplementalOxygen((bool) $observation['supplemental_oxygen']),
            'bp_systolic' => self::scoreSystolicBp((int) $observation['bp_systolic']),
            'pulse' => self::scorePulse((int) $observation['pulse']),
            'temperature_celsius' => self::scoreTemperature((float) $observation['temperature_celsius']),
            'consciousness_level' => self::scoreConsciousness((string) $observation['consciousness_level']),
        ];

        $totalScore = array_sum($componentScores);
        $flaggedParameters = collect($componentScores)
            ->filter(fn (int $score) => $score === 3)
            ->keys()
            ->map(fn (string $key) => self::parameterLabel($key))
            ->values()
            ->all();
        $hasSingleParameterThree = $flaggedParameters !== [];

        $riskLevel = self::resolveRiskLevel($totalScore, $hasSingleParameterThree);
        $guidance = config('news2.escalation_guidance.'.$riskLevel, '');

        return [
            'total_score' => $totalScore,
            'risk_level' => $riskLevel,
            'risk_label' => config('news2.risk_levels.'.$riskLevel, ucfirst(str_replace('_', ' ', $riskLevel))),
            'has_single_parameter_three' => $hasSingleParameterThree,
            'flagged_parameters' => $flaggedParameters,
            'component_scores' => $componentScores,
            'escalation_guidance' => $guidance,
            'oxygen_scale' => $oxygenScale,
        ];
    }

    public static function resolvePatientOxygenScale(Patient $patient): int
    {
        if (Schema::hasTable('patient_care_plan_forms')) {
            $form = PatientCarePlanForm::query()
                ->where('patient_slug', $patient->url_key)
                ->where('plan_slug', 'respiratory-care')
                ->first();

            $scale = (int) ($form?->data['news2_oxygen_scale'] ?? 0);
            if (in_array($scale, [1, 2], true)) {
                return $scale;
            }
        }

        $patientScale = (int) ($patient->news2_oxygen_scale ?? 0);

        return in_array($patientScale, [1, 2], true) ? $patientScale : 1;
    }

    public static function scoreRespirationRate(int $rate): int
    {
        if ($rate <= 8) {
            return 3;
        }
        if ($rate <= 11) {
            return 1;
        }
        if ($rate <= 20) {
            return 0;
        }
        if ($rate <= 24) {
            return 2;
        }

        return 3;
    }

    public static function scoreSpo2(int $spo2, int $scale = 1): int
    {
        if ($scale === 2) {
            if ($spo2 <= 83) {
                return 3;
            }
            if ($spo2 <= 85) {
                return 2;
            }
            if ($spo2 <= 87) {
                return 1;
            }
            if ($spo2 <= 92) {
                return 0;
            }
            if ($spo2 <= 94) {
                return 1;
            }
            if ($spo2 <= 96) {
                return 2;
            }

            return 3;
        }

        if ($spo2 <= 91) {
            return 3;
        }
        if ($spo2 <= 93) {
            return 2;
        }
        if ($spo2 <= 95) {
            return 1;
        }

        return 0;
    }

    public static function scoreSupplementalOxygen(bool $onOxygen): int
    {
        return $onOxygen ? 2 : 0;
    }

    public static function scoreSystolicBp(int $systolic): int
    {
        if ($systolic <= 90) {
            return 3;
        }
        if ($systolic <= 100) {
            return 2;
        }
        if ($systolic <= 110) {
            return 1;
        }
        if ($systolic <= 219) {
            return 0;
        }

        return 3;
    }

    public static function scorePulse(int $pulse): int
    {
        if ($pulse <= 40) {
            return 3;
        }
        if ($pulse <= 50) {
            return 1;
        }
        if ($pulse <= 90) {
            return 0;
        }
        if ($pulse <= 110) {
            return 2;
        }
        if ($pulse <= 130) {
            return 3;
        }

        return 3;
    }

    public static function scoreTemperature(float $celsius): int
    {
        if ($celsius <= 35.0) {
            return 3;
        }
        if ($celsius <= 36.0) {
            return 1;
        }
        if ($celsius <= 38.0) {
            return 0;
        }
        if ($celsius <= 39.0) {
            return 1;
        }

        return 2;
    }

    public static function scoreConsciousness(string $level): int
    {
        return $level === 'alert' ? 0 : 3;
    }

    public static function resolveRiskLevel(int $totalScore, bool $hasSingleParameterThree): string
    {
        if ($totalScore >= 7) {
            return self::RISK_HIGH;
        }

        if ($totalScore >= 5 || $hasSingleParameterThree) {
            return self::RISK_MEDIUM;
        }

        if ($totalScore >= 1) {
            return self::RISK_LOW_MEDIUM;
        }

        return self::RISK_LOW;
    }

    public static function parameterLabel(string $key): string
    {
        return match ($key) {
            'respiration_rate' => 'Respiration rate',
            'spo2' => 'Oxygen saturation',
            'supplemental_oxygen' => 'Supplemental oxygen',
            'bp_systolic' => 'Systolic BP',
            'pulse' => 'Pulse',
            'temperature_celsius' => 'Temperature',
            'consciousness_level' => 'Consciousness (ACVPU)',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    public static function consciousnessLabel(?string $level): ?string
    {
        if ($level === null || $level === '') {
            return null;
        }

        return config('news2.consciousness_levels.'.$level, ucfirst($level));
    }

    public static function requiresEscalation(string $riskLevel): bool
    {
        return in_array($riskLevel, [self::RISK_MEDIUM, self::RISK_HIGH], true);
    }

    public static function requiresManagerNotification(string $riskLevel): bool
    {
        return in_array($riskLevel, [self::RISK_LOW_MEDIUM, self::RISK_MEDIUM, self::RISK_HIGH], true);
    }
}
