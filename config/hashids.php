<?php

return [
    'salt' => env('HASHIDS_SALT', 'your-$ecret-$alt'),
    'min_length' => 15,
    'alphabet' => env('HASHED_ALPHABET'),
];

