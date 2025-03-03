<?php

return [
    'paths' => ['*'], // Engedélyezze az összes útvonalat
    'allowed_methods' => ['*'], // Engedélyezze az összes HTTP metódust
    'allowed_origins' => ['*'], // Engedélyezze az összes eredetet
    'allowed_headers' => ['*'], // Engedélyezze az összes fejlécet
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
