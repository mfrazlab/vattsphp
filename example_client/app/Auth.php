<?php

use models\User;
use Vatts\Auth\Providers\CredentialsProvider;
use Vatts\Auth\VattsAuth;

// Configuramos a instância
$auth = new VattsAuth([
    'providers' => [
        new CredentialsProvider([
            'authorize' => function ($credentials) {
                $email = $credentials['email'];
                $user = filter_var($email, FILTER_VALIDATE_EMAIL)
                    ? User::get('email', $email)
                    : User::get('name', $email);

                if ($user && password_verify($credentials['password'], $user->password)) {
                    return $user->toArray();
                }
                return null;
            }
        ]),
    ],
    'callbacks' => [
        // Altera o que vai ser SALVO na sessão ($_SESSION)
        'jwt' => function($user) {
            return [
                'id' => $user['id'],
            ];
        },
        // Altera o que o FRONT-END recebe quando bate no GET /api/auth/session
        'session' => function($sessionData) {
            $id = $sessionData['id'] ?? null;
            if ($id) {
                $user = User::get('id', $id);
                if ($user) {
                    return User::get('id', $id)->toArray();
                }
            }
            return $sessionData;
        }
    ]
]);

// Retornamos a instância para que possa ser capturada por outro arquivo
return $auth;