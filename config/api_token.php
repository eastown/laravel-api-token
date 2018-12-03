<?php

return [
    'user' => [
        'model' => \App\User::class,
        'with' => [],
        'credentials' => [
            'username', 'email', 'phone'
        ],
        'password_validator' => function($password, App\User $user){

        },
        'verify_fingerprint' => true
    ],

];