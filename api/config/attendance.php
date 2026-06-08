<?php

return [
    // Minimum attendance percentage a student must maintain to avoid detention
    'detention_threshold' => (float) env('DETENTION_THRESHOLD', 75),
];
