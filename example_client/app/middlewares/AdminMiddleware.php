<?php

namespace App\middlewares;

use models\User;
use Vatts\Utils\Middleware;
use Vatts\Router\Request;
use Vatts\Router\Response;

class AdminMiddleware extends Middleware
{
    public static string $name = 'admin';

    public function handle(Request $request, Response $response): Request
    {
        $auth = require __DIR__ . '/../Auth.php';
        $session = $auth->getSession();
        if($session !== null) {
            $request->setParsed('user', User::get('id', $session['id']));
            if($request->getParsed('user')->role !== 'admin') {
                $response->redirect('/');
                return $request;
            }
        } else {
            $request->setParsed('user', null);
            $response->redirect('/auth');
        }


        return $request;
    }
}


