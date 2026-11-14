<?php

/*
 * SEPA-Lastschrift: Format der Lastschriftdatei. pain.008.001.08 ist der
 * aktuelle Standard; ältere Bank-Software verlangt ggf. pain.008.001.02.
 */
return [
    'pain_version' => env('SEPA_PAIN_VERSION', 'pain.008.001.08'),
];
