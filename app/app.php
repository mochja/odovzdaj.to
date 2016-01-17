<?php

use App\Repository;
use App\Entity;
use App\Services;
use App\Form;
use SPE\FilesizeExtensionBundle\Twig\FilesizeExtension;

$config = require_once __DIR__ . '/config.php';

$app = new Silex\Application();

$app['debug'] = true;

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider());

$app->register(new Silex\Provider\TranslationServiceProvider(), [
    'locale_fallbacks' => ['en'],
]);

$app->register(new Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => $config['db'],
]);

$logger = new Doctrine\DBAL\Logging\DebugStack();
$app['db.config']->setSQLLogger($logger);

$app[Repository\Classroom::class] = $app->share(function () use ($app) {
    return new Repository\Classroom($app['db'], $app['session']);
});

$app[Repository\Subject::class] = $app->share(function () use ($app) {
    return new Repository\Subject($app['db'], $app['session']);
});

$app[Services\LoginService::class] = $app->share(function () use ($app) {
    return new Services\LoginService($app['db']);
});

$app[Repository\Entry::class] = $app->share(function () use ($app) {
    return new Repository\Entry($app['db'], $app['session']);
});

// Nacitanie twig-u
$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__ . '/views',
]);

$app['twig'] = $app->share($app->extend('twig', function (Twig_Environment $twig, $app) use ($logger) {
    $twig->addGlobal('logger', $logger);
    $twig->addGlobal('user', $app['session']->get('user'));

    $twig->addExtension(new FilesizeExtension());
    $twig->addFilter('rome', new Twig_SimpleFilter('rome', function ($val) {
        return $val < 4 ? str_repeat('I', $val) : 'IV';
    }));

    return $twig;
}));

$app->extend('form.types', function ($types, $app) {
    $types[Form\Entry::class] = new Form\Entry($app[Repository\Classroom::class], $app[Repository\Subject::class]);

    return $types;
});

return $app;
