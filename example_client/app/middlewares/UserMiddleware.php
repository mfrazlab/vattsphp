<?php

namespace App\Middlewares;

use models\User;
use Vatts\Utils\Middleware;
use Vatts\Router\Request;
use Vatts\Router\Response;

class UserMiddleware extends Middleware
{
    public static string $name = 'user';

    public function handle(Request $request, Response $response): Request
    {
        $auth = require __DIR__ . '/../Auth.php';
        $session = $auth->getSession();
        if($session !== null) {
            $request->setParsed('user', User::get('id', $session['id']));
        } else {
            $request->setParsed('user', null);
            $response->redirect('/auth');
        }

        return $request;
    }
}


