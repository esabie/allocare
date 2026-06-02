import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';
import { postWithOfflineQueue } from '@/utils/offlineQueue';

const sideTabs = [
    { label: 'Overview', key: 'overview' },
    { label: 'Care Plans', key: 'care_plans' },
    { label: 'Risk Assessment', key: 'risk_assessment' },
    { label: 'eMAR', key: 'medication' },
    { label: 'Observations', key: 'observations' },
    { label: 'Documents', key: 'documents' },
    { label: 'Notes', key: 'notes' },
    { label: 'Logs', key: 'logs' },
    { label: 'Contacts', key: 'contacts' },
    // { label: 'Alerts', key: 'alerts' },
];

function formatDocumentName(slug) {
    return slug
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function canEditAboutMeForm(user) {
    if (!user) return false;

    const normalize = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, '_');
    const roleAliases = {
        administrator: 'admin',
        care_staff: 'staff',
        support_staff: 'staff',
    };
    const allowedRoles = new Set(['admin', 'staff']);

    const roleCandidates = [
        user.role,
        user.role_name,
        user.user_role,
        user.role?.name,
        ...(Array.isArray(user.roles) ? user.roles.flatMap((entry) => [entry?.name, entry]) : []),
    ];

    return roleCandidates.some((role) => {
        const normalized = normalize(role);
        const canonical = roleAliases[normalized] || normalized;
        return allowedRoles.has(normalized) || allowedRoles.has(canonical);
    });
}

const aboutMeSections = [
    {
        title: 'IDENTITY',
        fields: [
            { label: 'Name (preferred name)', key: 'preferredName', type: 'text' },
            { label: 'Pronouns', key: 'pronouns', type: 'text' },
            { label: 'Date of birth', key: 'dateOfBirth', type: 'date' },
            { label: 'Primary language / Interpreter needed (Yes/No)', key: 'languageInterpreter', type: 'text' },
            { label: 'Religion / Culture (optional)', key: 'religionCulture', type: 'text' },
            { label: 'Capacity for this plan (Capable / Best interest summary & date)', key: 'capacitySummary', type: 'textarea' },
        ],
    },
    {
        title: 'WHAT MATTERS TO ME',
        fields: [
            { label: 'What makes a good day for me', key: 'goodDay', type: 'textarea' },
            { label: 'What I enjoy / hobbies', key: 'hobbies', type: 'textarea' },
            { label: 'What I dislike / want to avoid', key: 'dislikes', type: 'textarea' },
            { label: 'My strengths and how to support them', key: 'strengthsSupport', type: 'textarea' },
        ],
    },
    {
        title: 'HOW I COMMUNICATE',
        fields: [
            { label: 'How I like people to talk to me (tone, pace, style)', key: 'communicationStyle', type: 'textarea' },
            { label: 'How I express needs/pain/anxiety (words, signs, behaviours)', key: 'expressionSignals', type: 'textarea' },
            { label: 'Helpful responses from staff when I am distressed', key: 'distressResponses', type: 'textarea' },
        ],
    },
    {
        title: 'DAILY ROUTINES & PREFERENCES',
        fields: [
            { label: 'Morning routine (times, prompts, preferred order)', key: 'morningRoutine', type: 'textarea' },
            { label: 'Meals & drinks (likes, dislikes, texture/IDDSI, allergies)', key: 'mealsAndDrinks', type: 'textarea' },
            { label: 'Personal care (showering, hair/skin, shaving, menstruation)', key: 'personalCare', type: 'textarea' },
            { label: 'Clothes & appearance (preferences, sensory issues)', key: 'clothesAppearance', type: 'textarea' },
            { label: 'Mobility & equipment (aids, hoist + sling TYPE & SIZE, positioning limits)', key: 'mobilityEquipment', type: 'textarea' },
            { label: 'Activities & community (faith, clubs, outdoors, screen time)', key: 'activitiesCommunity', type: 'textarea' },
            { label: 'Sleep (bedtime routine, noise/light preferences)', key: 'sleepRoutine', type: 'textarea' },
        ],
    },
    {
        title: 'HEALTH & SAFETY ESSENTIALS',
        fields: [
            { label: 'Health conditions to be aware of (plain language)', key: 'healthConditions', type: 'textarea' },
            { label: 'Medication support I need (who helps, best times, PRN triggers)', key: 'medicationSupport', type: 'textarea' },
            { label: 'Eating & drinking risks (e.g., choking, hydration prompts)', key: 'eatingDrinkingRisks', type: 'textarea' },
            { label: 'Skin & pressure care (creams, checks, repositioning)', key: 'skinPressureCare', type: 'textarea' },
            { label: 'Seizure/diabetes/asthma/other key protocols (where they are, early signs)', key: 'keyProtocols', type: 'textarea' },
            { label: 'Known risks & what helps keep me safe (brief)', key: 'knownRisks', type: 'textarea' },
        ],
    },
    {
        title: 'IMPORTANT PEOPLE',
        fields: [
            { label: 'Who is important to me (names & relationship)', key: 'importantPeople', type: 'textarea' },
            { label: 'How and when I want them involved/updated', key: 'involvementPreferences', type: 'textarea' },
        ],
    },
    {
        title: 'MY GOALS',
        fields: [
            { label: 'Short term goals (next 1-3 months) - SMART', key: 'shortTermGoals', type: 'textarea' },
            { label: 'Long term goals (3-12 months) - SMART', key: 'longTermGoals', type: 'textarea' },
        ],
    },
    {
        title: 'ACCESSIBILITY & REASONABLE ADJUSTMENTS',
        fields: [
            { label: 'Things that help me access care (visuals, large print, quiet space, extra time)', key: 'accessibilityAdjustments', type: 'textarea' },
        ],
    },
    {
        title: 'CONSENT & REVIEWS',
        fields: [
            { label: 'Consent to share this plan with (names/roles)', key: 'consentShareWith', type: 'text' },
            { label: 'Date created', key: 'dateCreated', type: 'date' },
            { label: 'Next review date', key: 'nextReviewDate', type: 'date' },
            { label: 'Client/Representative signature & date', key: 'clientSignatureDate', type: 'text' },
            { label: 'Staff member completing', key: 'staffRole', type: 'text' },
        ],
    },
];

const communicationPassportSections = [
    {
        title: 'COMMUNICATION PASSPORT',
        fields: [
            { label: 'Name (preferred)', key: 'passportPreferredName', type: 'text' },
            { label: 'DOB / NHS no.', key: 'passportDobNhs', type: 'text' },
            { label: 'Primary language', key: 'passportPrimaryLanguage', type: 'text' },
            { label: 'Interpreter needed (Yes/No)', key: 'passportInterpreterNeeded', type: 'text' },
            { label: 'How to talk with me (tone, pace, simple steps) - what works', key: 'passportHowToTalk', type: 'textarea' },
            { label: 'What to avoid (e.g., noise, rushing, multiple questions)', key: 'passportAvoid', type: 'textarea' },
            { label: 'I understand best when... (pictures/gestures/objects/easy read)', key: 'passportUnderstandsBest', type: 'textarea' },
            { label: 'How I say YES / NO', key: 'passportYesNo', type: 'textarea' },
            { label: 'How I ask for things / make choices (AAC, device, signs)', key: 'passportAskChoices', type: 'textarea' },
            { label: 'How I show pain or anxiety', key: 'passportPainAnxiety', type: 'textarea' },
            { label: 'What helps me calm (top 3)', key: 'passportCalmTop3', type: 'textarea' },
            { label: 'Aids I use (hearing/vision/AAC) - Keep with me', key: 'passportAids', type: 'textarea' },
            { label: 'Reasonable adjustments (quiet space, extra time, support person)', key: 'passportAdjustments', type: 'textarea' },
            { label: 'Main contact (name - relationship - phone)', key: 'passportMainContact', type: 'text' },
            { label: 'If I go to hospital: important notes / adjustments needed', key: 'passportHospitalNotes', type: 'textarea' },
            { label: 'Last updated', key: 'passportLastUpdated', type: 'date' },
            { label: 'Next review', key: 'passportNextReview', type: 'date' },
        ],
    },
];

const hospitalPassportSections = [
    {
        title: 'Hospital Passport',
        fields: [
            { label: 'Service User Details', key: 'hospitalPassportServiceUserDetailsHeading', type: 'heading' },
            { label: 'Full Name', key: 'hospitalPassportFullName', type: 'text' },
            { label: 'Date of Birth', key: 'hospitalPassportDob', type: 'date' },
            { label: 'NHS Number', key: 'hospitalPassportNhsNumber', type: 'text' },
            { label: 'Address', key: 'hospitalPassportAddress', type: 'text' },
            { label: 'Emergency Contact', key: 'hospitalPassportEmergencyContact', type: 'text' },
            { label: 'Medical Overview', key: 'hospitalPassportMedicalOverviewHeading', type: 'heading' },
            { label: 'Primary Diagnoses', key: 'hospitalPassportPrimaryDiagnoses', type: 'textarea' },
            { label: 'Allergies', key: 'hospitalPassportAllergies', type: 'textarea' },
            { label: 'Key Risks', key: 'hospitalPassportKeyRisks', type: 'textarea' },
            { label: 'Medication', key: 'hospitalPassportMedicationHeading', type: 'heading' },
            { label: 'Regular Medication', key: 'hospitalPassportRegularMedication', type: 'textarea' },
            { label: 'PRN / Rescue Medication', key: 'hospitalPassportPrnMedication', type: 'textarea' },
            { label: 'Clinical Needs', key: 'hospitalPassportClinicalNeedsHeading', type: 'heading' },
            { label: 'Mobility', key: 'hospitalPassportMobility', type: 'textarea' },
            { label: 'Nutrition', key: 'hospitalPassportNutrition', type: 'textarea' },
            { label: 'Respiratory Support', key: 'hospitalPassportRespiratorySupport', type: 'textarea' },
            { label: 'Key Equipment', key: 'hospitalPassportKeyEquipmentHeading', type: 'heading' },
            { label: 'Equipment Required', key: 'hospitalPassportEquipmentRequired', type: 'textarea' },
            { label: 'Important Information', key: 'hospitalPassportImportantInfoHeading', type: 'heading' },
            { label: 'Early Warning Signs', key: 'hospitalPassportEarlyWarningSigns', type: 'textarea' },
            { label: 'Critical Notes for Staff', key: 'hospitalPassportCriticalNotes', type: 'textarea' },
        ],
    },
];

const advanceStatementSections = [
    {
        title: 'ADVANCE STATEMENT',
        fields: [
            { label: 'WHO I AM', key: 'advanceWhoIAmHeading', type: 'heading' },
            { label: 'Full name', key: 'advanceFullName', type: 'text' },
            { label: 'DOB / NHS no.', key: 'advanceDobNhs', type: 'text' },
            { label: 'Preferred name / pronouns', key: 'advancePreferredPronouns', type: 'text' },
            { label: 'Primary language / interpreter needed (Yes/No)', key: 'advanceLanguageInterpreter', type: 'text' },
            { label: 'WHAT MATTERS MOST', key: 'advanceWhatMattersHeading', type: 'heading' },
            { label: 'Top 3 things that matter to me', key: 'advanceTopThreeMatters', type: 'textarea' },
            { label: 'What helps me feel safe and calm', key: 'advanceSafeCalm', type: 'textarea' },
            { label: 'DAILY ESSENTIALS', key: 'advanceDailyEssentialsHeading', type: 'heading' },
            { label: 'How I like support with: washing/dressing, meals/drinks, sleep/comfort', key: 'advanceSupportPreferences', type: 'textarea' },
            { label: 'Food & drink: likes/dislikes, allergies (plain words)', key: 'advanceFoodDrink', type: 'textarea' },
            { label: 'Mobility & comfort aids (include sling TYPE & SIZE if used)', key: 'advanceMobilityAids', type: 'textarea' },
            { label: 'COMMUNICATION', key: 'advanceCommunicationHeading', type: 'heading' },
            { label: 'How to communicate with me; how I show pain/anxiety; what helps', key: 'advanceCommunicationNeeds', type: 'textarea' },
            { label: 'PEOPLE TO INVOLVE', key: 'advancePeopleHeading', type: 'heading' },
            { label: 'Names & phones of who I want involved in decisions', key: 'advancePeopleInvolved', type: 'textarea' },
            { label: 'PLACE OF CARE & EMERGENCIES', key: 'advancePlaceEmergenciesHeading', type: 'heading' },
            { label: 'Preferred place of care if more unwell (e.g., home/care home/hospice)', key: 'advancePreferredPlace', type: 'textarea' },
            { label: 'In an emergency, contact (names/roles/phones)', key: 'advanceEmergencyContacts', type: 'textarea' },
            { label: 'ADRT (Advance Decision to Refuse Treatment) - location', key: 'advanceAdrtLocation', type: 'text' },
            { label: 'DNACPR/RESPECT - location', key: 'advanceDnacprLocation', type: 'text' },
            { label: 'LPA Health & Welfare - attorney(s)', key: 'advanceLpaAttorneys', type: 'text' },
            { label: 'LPA Health & Welfare - location', key: 'advanceLpaLocation', type: 'text' },
            { label: 'REVIEW & SIGN', key: 'advanceReviewSignHeading', type: 'heading' },
            { label: 'Date completed', key: 'advanceDateCompleted', type: 'date' },
            { label: 'Review date (aim 6-12 months)', key: 'advanceReviewDate', type: 'date' },
            { label: 'My signature & date', key: 'advanceMySignatureDate', type: 'text' },
            { label: 'Supporter/professional (name/role/signature)', key: 'advanceSupporterSignature', type: 'text' },
        ],
    },
];

const initialAssessmentSections = [
    {
        title: 'Initial Assessment',
        fields: [
            { label: 'Assessment Details', key: 'initialAssessmentDetailsHeading', type: 'heading' },
            { label: 'Date', key: 'initialAssessmentDate', type: 'date' },
            { label: 'Assessed By', key: 'initialAssessmentAssessedBy', type: 'text' },
            { label: 'Location', key: 'initialAssessmentLocation', type: 'text' },
            { label: 'Service User Overview', key: 'initialAssessmentServiceUserOverviewHeading', type: 'heading' },
            { label: 'Background Summary', key: 'initialAssessmentBackgroundSummary', type: 'textarea' },
            { label: 'Living Situation', key: 'initialAssessmentLivingSituation', type: 'textarea' },
            { label: 'Medical History', key: 'initialAssessmentMedicalHistoryHeading', type: 'heading' },
            { label: 'Diagnoses', key: 'initialAssessmentDiagnoses', type: 'textarea' },
            { label: 'Recent Admissions', key: 'initialAssessmentRecentAdmissions', type: 'textarea' },
            { label: 'Current Needs', key: 'initialAssessmentCurrentNeedsHeading', type: 'heading' },
            { label: 'Personal Care', key: 'initialAssessmentPersonalCare', type: 'textarea' },
            { label: 'Mobility', key: 'initialAssessmentMobility', type: 'textarea' },
            { label: 'Medication', key: 'initialAssessmentMedication', type: 'textarea' },
            { label: 'Nutrition', key: 'initialAssessmentNutrition', type: 'textarea' },
            { label: 'Behavioural Support', key: 'initialAssessmentBehaviouralSupport', type: 'textarea' },
            { label: 'Risk Overview', key: 'initialAssessmentRiskOverviewHeading', type: 'heading' },
            { label: 'Falls', key: 'initialAssessmentFalls', type: 'textarea' },
            { label: 'Skin Integrity', key: 'initialAssessmentSkinIntegrity', type: 'textarea' },
            { label: 'Choking', key: 'initialAssessmentChoking', type: 'textarea' },
            { label: 'Behavioural Risks', key: 'initialAssessmentBehaviouralRisks', type: 'textarea' },
            { label: 'Capacity & Consent', key: 'initialAssessmentCapacityConsentHeading', type: 'heading' },
            { label: 'Capacity Status', key: 'initialAssessmentCapacityStatus', type: 'text' },
            { label: 'Consent Obtained', key: 'initialAssessmentConsentObtained', type: 'text' },
            { label: 'Care Package Requirements', key: 'initialAssessmentCarePackageHeading', type: 'heading' },
            { label: 'Hours', key: 'initialAssessmentHours', type: 'text' },
            { label: 'Staffing Level', key: 'initialAssessmentStaffingLevel', type: 'text' },
            { label: 'Skills Required', key: 'initialAssessmentSkillsRequired', type: 'textarea' },
        ],
    },
];

const baselineSummarySections = [
    {
        title: 'Baseline Summary',
        fields: [
            { label: 'Service User Details', key: 'baselineServiceUserDetailsHeading', type: 'heading' },
            { label: 'Name', key: 'baselineName', type: 'text' },
            { label: 'Date of Birth', key: 'baselineDob', type: 'date' },
            { label: 'NHS Number', key: 'baselineNhsNumber', type: 'text' },
            { label: 'Clinical Snapshot', key: 'baselineClinicalSnapshotHeading', type: 'heading' },
            { label: 'Primary Diagnosis', key: 'baselinePrimaryDiagnosis', type: 'textarea' },
            { label: 'Key Conditions', key: 'baselineKeyConditions', type: 'textarea' },
            { label: 'Current Presentation', key: 'baselineCurrentPresentationHeading', type: 'heading' },
            { label: 'Baseline Behaviour', key: 'baselineBehaviour', type: 'textarea' },
            { label: 'Communication Ability', key: 'baselineCommunicationAbility', type: 'textarea' },
            { label: 'Key Risks', key: 'baselineKeyRisksHeading', type: 'heading' },
            { label: 'Seizures', key: 'baselineSeizures', type: 'textarea' },
            { label: 'Choking', key: 'baselineChoking', type: 'textarea' },
            { label: 'Falls', key: 'baselineFalls', type: 'textarea' },
            { label: 'Behaviour', key: 'baselineRiskBehaviour', type: 'textarea' },
            { label: 'Daily Support Needs', key: 'baselineDailySupportHeading', type: 'heading' },
            { label: 'Personal Care', key: 'baselinePersonalCare', type: 'textarea' },
            { label: 'Mobility', key: 'baselineMobility', type: 'textarea' },
            { label: 'Nutrition', key: 'baselineNutrition', type: 'textarea' },
            { label: 'Medication', key: 'baselineMedication', type: 'textarea' },
            { label: 'Emergency Information', key: 'baselineEmergencyInfoHeading', type: 'heading' },
            { label: 'Early Warning Signs', key: 'baselineEarlyWarningSigns', type: 'textarea' },
            { label: 'Emergency Actions', key: 'baselineEmergencyActions', type: 'textarea' },
        ],
    },
];

const tissueViabilitySections = [
    {
        title: 'TISSUE VIABILITY CHECKLIST',
        fields: [
            { label: 'Name of the service user', key: 'tissueServiceUserName', type: 'text' },
            { label: 'DOB', key: 'tissueDob', type: 'date' },
            { label: 'Date', key: 'tissueAssessmentDate', type: 'date' },
            { label: 'Time (24-hour HH:MM)', key: 'tissueAssessmentTime', type: 'text' },
            { label: '1. WOUND LOCATION & DESCRIPTION', key: 'tissueSection1Heading', type: 'heading' },
            { label: 'Wound Site', key: 'tissueWoundSite', type: 'text' },
            { label: 'Wound Type', key: 'tissueWoundType', type: 'text' },
            { label: 'Appearance', key: 'tissueAppearance', type: 'text' },
            { label: 'Exudate Amount / Colour', key: 'tissueExudateAmountColour', type: 'text' },
            { label: '2. WOUND MEASUREMENTS', key: 'tissueSection2Heading', type: 'heading' },
            { label: 'Length (cm)', key: 'tissueLengthCm', type: 'text' },
            { label: 'Width (cm)', key: 'tissueWidthCm', type: 'text' },
            { label: 'Depth (cm)', key: 'tissueDepthCm', type: 'text' },
            { label: 'Undermining / Tunnelling', key: 'tissueUnderminingTunnelling', type: 'text' },
            { label: '3. SKIN AROUND THE WOUND (PERIWOUND)', key: 'tissueSection3Heading', type: 'heading' },
            { label: 'Condition', key: 'tissuePeriwoundCondition', type: 'text' },
            { label: '4. PAIN ASSESSMENT', key: 'tissueSection4Heading', type: 'heading' },
            { label: 'Pain Score (0-10)', key: 'tissuePainScore', type: 'text' },
            { label: 'Pain behaviours observed', key: 'tissuePainBehavioursObserved', type: 'text' },
            { label: '5. CURRENT DRESSING USED', key: 'tissueSection5Heading', type: 'heading' },
            { label: 'Cleansing Solution', key: 'tissueCleansingSolution', type: 'text' },
            { label: 'Products Applied', key: 'tissueProductsApplied', type: 'text' },
            { label: 'Dressing Intact After Application? Yes/No', key: 'tissueDressingIntactAfterApplication', type: 'text' },
            { label: '6. PRESSURE AREA MANAGEMENT', key: 'tissueSection6Heading', type: 'heading' },
            { label: 'Repositioning Frequency', key: 'tissueRepositioningFrequency', type: 'text' },
            { label: 'Equipment Checks', key: 'tissueEquipmentChecks', type: 'text' },
            { label: '7. CONTRIBUTING FACTORS THIS SHIFT', key: 'tissueSection7Heading', type: 'heading' },
            { label: 'Factors', key: 'tissueContributingFactorsThisShift', type: 'text' },
            { label: '8. INFECTION SCREENING', key: 'tissueSection8Heading', type: 'heading' },
            { label: 'Signs of Infection', key: 'tissueSignsOfInfection', type: 'text' },
            { label: 'Escalation Required? Yes/No', key: 'tissueEscalationRequired', type: 'text' },
            { label: '9. PHOTO & BODY MAP', key: 'tissueSection9Heading', type: 'heading' },
            { label: 'Photo Taken? Yes/No', key: 'tissuePhotoTaken', type: 'text' },
            { label: 'Body Map Updated? Yes/No', key: 'tissueBodyMapUpdated', type: 'text' },
            { label: 'Body map notes (Front/Back and location markers)', key: 'tissueBodyMapNotes', type: 'textarea' },
            { label: '10. PLAN & ACTIONS', key: 'tissueSection10Heading', type: 'heading' },
            { label: 'Actions', key: 'tissuePlanActions', type: 'text' },
            { label: 'NURSE SIGN-OFF', key: 'tissueSectionSignOffHeading', type: 'heading' },
            { label: 'Name', key: 'tissueNurseName', type: 'text' },
            { label: 'Signature', key: 'tissueNurseSignature', type: 'text' },
            { label: 'Time (24-hour HH:MM)', key: 'tissueNurseSignOffTime', type: 'text' },
        ],
    },
];

const activityLogRows = Array.from({ length: 6 }, (_, index) => index + 1);

export default function PatientDocumentDetail({ patientSlug = 'cr-88210', documentSlug = 'dnacpr-form' }) {
    const { auth, initialFormData = {}, savedSubmittedAt = null, canEditDocumentForm = null } = usePage().props;
    const successMessage = usePage().props?.flash?.success;
    const currentUser = auth?.user;
    const documentName = formatDocumentName(documentSlug);
    const isAboutMePlan = documentSlug === 'about-me-person-centred-care-plan';
    const isCommunicationPassport = documentSlug === 'communication-passport';
    const isHospitalPassport = documentSlug === 'hospital-passport';
    const isAdvanceStatement = documentSlug === 'advance-statement';
    const isInitialAssessment = documentSlug === 'initial-assessment';
    const isBaselineSummary = documentSlug === 'baseline-summary';
    const isActivityLog = documentSlug === 'activity-log-daily-record';
    const isTissueViability = documentSlug === 'tissue-viability-checklist';
    const isStructuredCareForm = isAboutMePlan || isCommunicationPassport || isHospitalPassport || isAdvanceStatement || isInitialAssessment || isBaselineSummary || isActivityLog || isTissueViability;
    const selectedSections = isAboutMePlan
        ? aboutMeSections
        : isCommunicationPassport
            ? communicationPassportSections
            : isHospitalPassport
                ? hospitalPassportSections
                : isAdvanceStatement
                    ? advanceStatementSections
                    : isInitialAssessment
                        ? initialAssessmentSections
                        : isBaselineSummary
                            ? baselineSummarySections
                            : isTissueViability
                                ? tissueViabilitySections
                                : [];
    const canEditForm = canEditDocumentForm ?? canEditAboutMeForm(currentUser);
    const [formData, setFormData] = useState(initialFormData || {});
    const [lastSavedData, setLastSavedData] = useState(initialFormData || {});
    const [isSubmitted, setIsSubmitted] = useState(Boolean(savedSubmittedAt));
    const [isEditing, setIsEditing] = useState(false);
    const [systemNow, setSystemNow] = useState(new Date());
    const [isOnline, setIsOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);
    const roleCandidates = [
        currentUser?.role,
        currentUser?.role_name,
        currentUser?.user_role,
        currentUser?.role?.name,
        ...(Array.isArray(currentUser?.roles) ? currentUser.roles.map((entry) => entry?.name || entry) : []),
    ];
    const detectedRole = roleCandidates.find((role) => String(role || '').trim().length > 0);
    const staffMemberAndRole = [
        currentUser?.name,
        detectedRole ? String(detectedRole).trim() : '',
    ]
        .filter(Boolean)
        .join(' - ')
        .toUpperCase();
    const isReadOnlyView = !canEditForm || (isSubmitted && !isEditing);
    const systemDateValue = systemNow.toISOString().slice(0, 10);
    const systemTimeValue = systemNow.toTimeString().slice(0, 5);

    useEffect(() => {
        if (!isTissueViability) {
            return undefined;
        }

        const intervalId = window.setInterval(() => {
            setSystemNow(new Date());
        }, 1000);

        return () => window.clearInterval(intervalId);
    }, [isTissueViability]);

    useEffect(() => {
        if (!isStructuredCareForm) return;
        const safeData = initialFormData || {};
        setFormData(safeData);
        setLastSavedData(safeData);
        setIsSubmitted(Boolean(savedSubmittedAt));
        setIsEditing(false);
    }, [isStructuredCareForm, patientSlug, documentSlug, initialFormData, savedSubmittedAt]);

    const setFieldValue = (key, value) => {
        setFormData((prev) => ({
            ...prev,
            [key]: value,
        }));
    };

    const handleStructuredFormSubmit = (event) => {
        event.preventDefault();

        if (!canEditForm) {
            return;
        }

        postWithOfflineQueue(
            route('patients.documents.save', { patient: patientSlug, document: documentSlug }),
            { data: formData },
            {
                onSuccess: () => {
                    setLastSavedData(formData);
                    setIsSubmitted(true);
                    setIsEditing(false);
                },
                onQueued: () => {
                    setLastSavedData(formData);
                    setIsSubmitted(true);
                    setIsEditing(false);
                },
            },
        );
    };

    const handleStructuredFormCancel = () => {
        if (!canEditForm) {
            return;
        }

        if (isSubmitted) {
            setFormData(lastSavedData || {});
            setIsEditing(false);
            return;
        }

        setFormData({});
    };

    const renderActivityLogInput = (key, options = {}) => {
        const { type = 'text', placeholder = '', required = true } = options;
        const isTimeField = type === 'time';
        return (
            <input
                type={isTimeField ? 'text' : type}
                value={formData[key] ?? ''}
                onChange={(event) => setFieldValue(key, event.target.value)}
                className="w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700"
                placeholder={isTimeField ? 'HH:MM' : placeholder}
                readOnly={isReadOnlyView}
                required={required}
                inputMode={isTimeField ? 'numeric' : undefined}
                pattern={isTimeField ? '^([01][0-9]|2[0-3]):[0-5][0-9]$' : undefined}
            />
        );
    };

    const renderField = (field) => {
        if (field.type === 'heading') {
            return <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{field.label}</p>;
        }

        const commonClasses = 'mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-emerald-400 focus:bg-white focus:outline-none';
        const isSystemDateField = field.key === 'tissueAssessmentDate';
        const isSystemTimeField = field.key === 'tissueAssessmentTime' || field.key === 'tissueNurseSignOffTime';
        const value = isSystemDateField
            ? systemDateValue
            : isSystemTimeField
                ? systemTimeValue
                : field.key === 'staffRole'
                    ? staffMemberAndRole
                    : formData[field.key] ?? '';

        const isOptional = field.label?.toLowerCase().includes('(optional)');

        if (field.type === 'textarea') {
            return (
                <textarea
                    value={value}
                    onChange={(event) => setFieldValue(field.key, event.target.value)}
                    className={`${commonClasses} min-h-[96px]`}
                    placeholder="Enter details"
                    required={!isOptional}
                    readOnly={isReadOnlyView}
                />
            );
        }

        return (
            <input
                type={field.type || 'text'}
                value={value}
                onChange={(event) => setFieldValue(field.key, event.target.value)}
                className={commonClasses}
                placeholder="Enter details"
                readOnly={field.key === 'staffRole' || isSystemDateField || isSystemTimeField || isReadOnlyView}
                required={!isOptional}
            />
        );
    };

    useEffect(() => {
        const onOnline = () => setIsOnline(true);
        const onOffline = () => setIsOnline(false);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    }, []);

    return (
        <>
            <Head title={`${documentName} - Document`} />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:flex lg:flex-col">
                        <div className="mb-5">
                            <Link href={route('dashboard')}>
                                <ApplicationLogo className="mb-3 block w-full" />
                            </Link>
                            <div className="rounded-xl border border-slate-200 bg-white p-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Patient Record</p>
                            </div>
                        </div>
                        <nav className="space-y-1.5">
                            {sideTabs.map((tab) =>
                                tab.key === 'overview' ? (
                                    <Link key={tab.key} href={route('patients.show', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'care_plans' ? (
                                    <Link key={tab.key} href={route('patients.careplans', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'risk_assessment' ? (
                                    <Link key={tab.key} href={route('patients.risks', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'medication' ? (
                                    <Link key={tab.key} href={route('patients.mar', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'observations' ? (
                                    <Link key={tab.key} href={route('patients.observations', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'documents' ? (
                                    <Link key={tab.key} href={route('patients.documents', patientSlug)} className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700">{tab.label}</Link>
                                ) : tab.key === 'logs' ? (
                                    <Link key={tab.key} href={route('patients.logs', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link key={tab.key} href={route('patients.contacts', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : (
                                    <button key={tab.key} type="button" className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</button>
                                ),
                            )}
                        </nav>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        {!isOnline && (
                            <section className="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-800">
                                Offline mode: document form saves are queued and will sync automatically when online.
                            </section>
                        )}
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <Link href={route('patients.documents', patientSlug)} className="hover:text-slate-700">Documents</Link>
                            <span>/</span>
                            <span className="text-slate-900">{documentName}</span>
                        </div>

                        {isStructuredCareForm ? (
                            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div className="mb-6 flex items-center justify-between">
                                    <h1 className="text-3xl font-bold text-slate-900">
                                        {isAboutMePlan
                                            ? 'ABOUT ME - PERSON CENTRED CARE PLAN'
                                            : isCommunicationPassport
                                                ? 'COMMUNICATION PASSPORT'
                                                : isHospitalPassport
                                                    ? 'HOSPITAL PASSPORT'
                                                    : isAdvanceStatement
                                                        ? 'ADVANCE STATEMENT'
                                                        : isInitialAssessment
                                                            ? 'INITIAL ASSESSMENT'
                                                            : isBaselineSummary
                                                                ? 'BASELINE SUMMARY'
                                                                : isActivityLog
                                                                    ? 'ACTIVITY LOG - DAILY RECORD'
                                                                    : 'TISSUE VIABILITY CHECKLIST'}
                                    </h1>
                                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Care Plan</span>
                                </div>
                                <p className="mb-6 text-sm text-slate-600">
                                    {isAboutMePlan
                                        ? 'Complete this profile to guide person-centred support and daily care delivery.'
                                        : isCommunicationPassport
                                            ? 'Capture key communication needs and adjustments to support safe, effective interactions.'
                                            : isHospitalPassport
                                                ? 'Provide essential admission details so hospital teams can deliver safe and appropriate care quickly.'
                                                : isAdvanceStatement
                                                    ? 'Document what matters most, preferred care, and key legal/emergency information.'
                                                    : isInitialAssessment
                                                        ? 'Record baseline needs, risks, and care package requirements at first assessment.'
                                                        : isBaselineSummary
                                                            ? 'Summarize baseline presentation, key risks, and support needs for ongoing care delivery.'
                                                            : isActivityLog
                                                                ? 'Track daily activities, outcomes, risks, and sign-off details in one record.'
                                                                : 'Record wound status, dressing care, pressure area management, and sign-off each review.'}
                                </p>
                                {!canEditForm && (
                                    <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                                        This form is view-only for your role. Only Admin and Staff can update this form.
                                    </p>
                                )}
                                {canEditForm && isSubmitted && !isEditing && (
                                    <p className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
                                        This form has already been completed. Click Edit to update any information.
                                    </p>
                                )}
                                {successMessage && (
                                    <p className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
                                        {successMessage}
                                    </p>
                                )}

                                <form onSubmit={handleStructuredFormSubmit}>
                                    <div className="space-y-6">
                                        {selectedSections.map((section) => (
                                            <article key={section.title} className="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                                                <h2 className="text-sm font-bold uppercase tracking-wide text-slate-700">{section.title}</h2>
                                                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    {section.fields.map((field) => (
                                                        <div key={field.key} className={field.type === 'textarea' || field.type === 'heading' ? 'md:col-span-2' : ''}>
                                                            {field.type !== 'heading' && <label className="text-sm font-medium text-slate-700">{field.label}</label>}
                                                            {renderField(field)}
                                                        </div>
                                                    ))}
                                                </div>
                                            </article>
                                        ))}
                                    </div>

                                    {isActivityLog && (
                                        <div className="space-y-5 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                                <div>
                                                    <label className="text-xs font-semibold text-slate-600">Client Name</label>
                                                    {renderActivityLogInput('activityLogClientName')}
                                                </div>
                                                <div>
                                                    <label className="text-xs font-semibold text-slate-600">Date</label>
                                                    {renderActivityLogInput('activityLogDate', { type: 'date' })}
                                                </div>
                                                <div>
                                                    <label className="text-xs font-semibold text-slate-600">Staff (name/role)</label>
                                                    {renderActivityLogInput('activityLogStaffNameRole')}
                                                </div>
                                            </div>

                                            <div>
                                                <label className="text-xs font-semibold text-slate-600">Care Plan / Goal Reference (optional)</label>
                                                {renderActivityLogInput('activityLogCarePlanGoalReference', { required: false })}
                                            </div>

                                            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                                <table className="w-full min-w-[1180px] border-collapse text-xs">
                                                    <thead className="bg-slate-50 text-slate-600">
                                                        <tr>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Start</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Finish</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Activity</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Location</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">With (who)</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Purpose / Goal link</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Support Provided</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Outcome & Notes</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Risks / Incidents</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Mileage / Mode</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Expenses GBP</th>
                                                            <th className="border border-slate-200 px-2 py-2 text-left">Initials</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {activityLogRows.map((rowNumber) => (
                                                            <tr key={rowNumber}>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}Start`, { type: 'time' })}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}Finish`, { type: 'time' })}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}Activity`)}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}Location`)}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}WithWho`)}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}PurposeGoal`)}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}SupportProvided`)}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}OutcomeNotes`)}</td>
                                                                <td className="border border-slate-200 p-1.5">
                                                                    <select
                                                                        value={formData[`activityLogRow${rowNumber}RisksIncidents`] ?? 'No'}
                                                                        onChange={(event) => setFieldValue(`activityLogRow${rowNumber}RisksIncidents`, event.target.value)}
                                                                        className="w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700"
                                                                        disabled={isReadOnlyView}
                                                                    >
                                                                        <option value="No">No</option>
                                                                        <option value="Yes">Yes</option>
                                                                    </select>
                                                                </td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}MileageMode`)}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}Expenses`)}</td>
                                                                <td className="border border-slate-200 p-1.5">{renderActivityLogInput(`activityLogRow${rowNumber}Initials`)}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div>
                                                <p className="mb-2 text-sm font-bold text-slate-700">Daily Summary & Totals</p>
                                                <label className="text-xs font-semibold text-slate-600">Total time in activities (hh:mm)</label>
                                                {renderActivityLogInput('activityLogTotalTime')}
                                            </div>

                                            <div>
                                                <p className="mb-2 text-sm font-bold text-slate-700">Sign-off</p>
                                                <div className="space-y-3">
                                                    <div>
                                                        <label className="text-xs font-semibold text-slate-600">Staff signature / initials</label>
                                                        {renderActivityLogInput('activityLogStaffSignature')}
                                                    </div>
                                                    <div>
                                                        <label className="text-xs font-semibold text-slate-600">Manager review (if required)</label>
                                                        {renderActivityLogInput('activityLogManagerReview')}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
                                        {canEditForm && isSubmitted && !isEditing && (
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setIsEditing(true);
                                                }}
                                                className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                            >
                                                Edit
                                            </button>
                                        )}
                                        {isActivityLog && (
                                            <button
                                                type="button"
                                                onClick={() => window.print()}
                                                className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                            >
                                                Print
                                            </button>
                                        )}
                                        <button
                                            type="submit"
                                            disabled={!canEditForm || (isSubmitted && !isEditing)}
                                            className={`rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition ${
                                                canEditForm && (!isSubmitted || isEditing)
                                                    ? 'hover:bg-slate-800'
                                                    : 'cursor-not-allowed opacity-70'
                                            }`}
                                        >
                                            {isActivityLog ? 'Submit' : 'Save & Finalize Record'}
                                        </button>
                                        <button
                                            type="button"
                                            disabled={!canEditForm}
                                            onClick={handleStructuredFormCancel}
                                            className={`rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold transition ${
                                                canEditForm
                                                    ? 'bg-white text-slate-700 hover:bg-slate-50'
                                                    : 'cursor-not-allowed bg-slate-100 text-slate-400'
                                            }`}
                                        >
                                            Cancel & Discard
                                        </button>
                                    </div>
                                </form>
                            </section>
                        ) : (
                            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div className="mb-4 flex items-center justify-between">
                                    <h1 className="text-3xl font-bold text-slate-900">{documentName}</h1>
                                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">Document</span>
                                </div>
                                <p className="mb-6 max-w-3xl text-sm text-slate-600">
                                    Document details and review workspace for {documentName}. Track updates, approval notes, and ownership.
                                </p>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="rounded-xl bg-slate-50 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Last Updated</p>
                                        <p className="mt-1 text-lg font-semibold text-slate-900">14 Oct 2024</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Owner</p>
                                        <p className="mt-1 text-lg font-semibold text-slate-900">Nurse Sarah-Jane</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Version</p>
                                        <p className="mt-1 text-lg font-semibold text-slate-900">v1.2</p>
                                    </div>
                                </div>
                            </section>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
