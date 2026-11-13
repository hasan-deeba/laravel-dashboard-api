<?php

return [
    // Path to a Firebase Admin SDK service-account JSON file, set in the server environment.
    'credentials' => env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),
];
