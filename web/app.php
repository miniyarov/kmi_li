<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config/parameters.php';

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();

$app['facebook'] = new Facebook(array(
    'appId' => FACEBOOK_APPID,
    'secret' => FACEBOOK_APPSECRET
));

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views'
));
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\DoctrineServiceProvider(), $config_database);

$app->register(new Silex\Provider\SessionServiceProvider());

$app->get('/', function () use ($app) {

    return $app['twig']->render('index.html.twig');

})->bind('homepage');

$app->get('/nasil-katilirim', function () use ($app) {
    return 'nasil-katilirim';
})->bind('nasil_katilirim');

$app->get('/hemen-katil', function () use ($app) {

    $boxes = $app['db']->fetchAssoc("SELECT boxid FROM boxes");

    $box = array();
    foreach ($boxes as $boxid) {
        $box[(int) $boxid] = true;
    }

    return $app['twig']->render('join.html.twig', array(
        'box' => $box
    ));
})->bind('hemen_katil');

$app->get('/show-boxes', function (Request $request) use ($app) {

    $photos = $app['db']->fetchAssoc("SELECT * FROM photos WHERE boxid = :boxid", array('boxid' => $request->query->get('boxid')));

    $refactoredPhotos = array();

    foreach ($photos as $photo) {
        $refactoredPhotos[(int) $photo['photoid']] = true;
    }

    return $app['twig']->render('show_boxes.html.twig', array(
        'boxid' => $request->query->get('boxid'),
        'photos' => $refactoredPhotos
    ));
})->bind('show_boxes');

$app->get('/show_photo', function () use ($app) {

})->bind('show_photo');

$app->get('/photo_upload', function () use ($app) {

})->bind('photo_upload');



$app->get('/login/', function () use ($app) {

    $userId = $app['facebook']->getUser();

    if ($userId) {
        try {
            $user = $app['mongo']->silex->users->findOne(array('userid' => $userId));

            $userInfo = $app['facebook']->api('/me');

            if (!$user) {

                $app['mongo']->silex->users->insert(array(
                    'userid' => $userId,
                    'username' => $userInfo['username'],
                    'email' => $userInfo['email'],
                    'firstname' => $userInfo['first_name'],
                    'lastname' => $userInfo['last_name'],
                    'gender' => $userInfo['gender'],
                    'roles' => array('ROLE_ADMIN'),
                    'createdAt' => new \MongoDate(),
                    'accessedAt' => new \MongoDate()
                ));

            } else {

                $user['accessedAt'] = new \MongoDate();

                $app['mongo']->silex->users->save($user);
            }

            $app['session']->set('userid', $userId);

            return $app->redirect($app['url_generator']->generate('admin'));
        } catch (FacebookApiException $e) {
            error_log($e->getMessage());
        }
    }

    $app['session']->set('userid', null);

    return $app['twig']->render('login.html.twig', array(
        'title' => 'Login'
    ));
})->bind('login');

$app->get('/logout/', function () use ($app) {
    $facebookLogoutUrl = $app['facebook']->getLogoutUrl(array('next' => $app['url_generator']->generate('homepage', array(), true)));

    $app['session']->set('userid', null);

    return $app->redirect($facebookLogoutUrl);
})->bind('logout');

$app->get('/admin/', function () use ($app) {

    if (null === $userid = $app['session']->get('userid', null)) {
        return $app->redirect($app['url_generator']->generate('login'));
    }

    return $app['twig']->render('admin.html.twig', array(
        'title' => 'Admin'
    ));
})->bind('admin');

$app->post('/post/{action}/{id}', function (\Symfony\Component\HttpFoundation\Request $request, $action, $id) use ($app) {

    if ('add' == $action) {
        $app['mongo']->silex->posts->insert(array(
            'slug' => $request->request->get('title'),
            'title' => $request->request->get('title'),
            'content' => $request->request->get('content'),
            'createdAt' => new \MongoDate(),
            'updatedAt' => new \MongoDate()
        ));
    }
})->bind('post');

$app->run();