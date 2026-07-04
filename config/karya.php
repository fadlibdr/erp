<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| KARYA product configuration
|--------------------------------------------------------------------------
| Product-wide knobs. Statutory *rates* live in the database (effective-dated),
| not here — this file holds only defaults and feature switches.
*/

return [
    // Default output-VAT rate. 11% today; flip to 12 when the increase applies.
    'ppn_rate_percent' => env('KARYA_PPN_RATE', 11),

    // Deployment profile — drives resource-sensitive defaults (queue/cache driver,
    // Gotenberg on/off). See docker/ and the README sizing table.
    'profile' => env('KARYA_PROFILE', 'standard'), // minimal | standard

    // e-Faktur channel preference. File export is always available as a fallback.
    'efaktur' => [
        'channel' => env('KARYA_EFAKTUR_CHANNEL', 'file'), // file | coretax_api | pjap
    ],
];
