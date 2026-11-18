<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Current Academic Session Start Year
    |--------------------------------------------------------------------------
    |
    | Used as default baseline for calculating suggested academic levels when
    | level is omitted in student registry imports or manual registrations.
    | Formula: suggested_level = ((current_session_start_year - admission_year) + 1) * 100
    |
    */
    'current_session_start_year' => (int) env('CURRENT_SESSION_START_YEAR', 2025),

    /*
    |--------------------------------------------------------------------------
    | Maximum / Minimum Academic Level
    |--------------------------------------------------------------------------
    |
    | Maximum supported programme level (e.g. 500L for 5-year programmes).
    |
    */
    'max_level' => (int) env('ACADEMIC_MAX_LEVEL', 500),
    'min_level' => (int) env('ACADEMIC_MIN_LEVEL', 100),
];
