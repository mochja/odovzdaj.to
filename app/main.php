<?php

require __DIR__.'/config.php';

date_default_timezone_set('Europe/Bratislava');

use Symfony\Component\HttpFoundation\RedirectResponse;
use SPE\FilesizeExtensionBundle\Twig\FilesizeExtension;

$app = new Silex\Application();

$app['debug']= true;

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider());

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('en'),
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => $config['db'],
));

$logger = new Doctrine\DBAL\Logging\DebugStack();
$app['db.config']->setSQLLogger($logger);

$app['repository.triedy'] = $app->share(function () use ($app) {
    return new TriedyRepository($app['db'], $app['session']);
});

$app['repository.predmety'] = $app->share(function () use ($app) {
    return new PredmetyRepository($app['db'], $app['session']);
});

$app['login_service'] = $app->share(function () use ($app) {
    return new IMAPLoginService($app['db']);
});

$app['zadania_service'] = $app->share(function () use ($app) {
    return new ZadaniaService($app['db'], $app['session']);
});

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) use ($logger) {
    $twig->addGlobal('logger', $logger);
    $twig->addGlobal('user', $app['session']->get('user'));

    $twig->addExtension(new FilesizeExtension());
    $twig->addFilter('rome', new Twig_SimpleFilter('rome', function ($val) { 
        return $val < 4 ? str_repeat('I', $val) : 'IV';        
    }));
    return $twig;
}));

$checkUser = function ($request) use ($app) {
    $user = $app['session']->get('user');
    if ( empty($user) ) {
        return new RedirectResponse( $app['url_generator']->generate('login') );
    }
};

$app->get('/', function () use ($app) {
    $user = $app['session']->get('user');

    // Pre studenta zobrazime len shit
    if ($user['role'] == 1)
    {
        $zadania = $app['zadania_service']->getAll();
        $ukonceneZadania = $app['zadania_service']->getAll(TRUE);

        return $app['twig']->render('home_student.twig', compact('zadania', 'ukonceneZadania'));
    }
    else if ($user['role'] == 2) // pre ucitela viac shitov
    {
        $form = $app['form.factory']->create(new ZadanieForm($app['repository.triedy'], $app['repository.predmety']), new Zadanie());
        
        return $app['twig']->render('home_teacher.twig', array(
            'zadaniaZatvorene' => $app['zadania_service']->getAllForTeacher(TRUE),
            'zadania' => $app['zadania_service']->getAllForTeacher(), 
            'form' => $form->createView()
        ));
    }
    
})->before($checkUser)        
->bind('home');

$app->get('/goodbye', function () use ($app) {
    $app['session']->clear();
    $app['session']->getFlashBag()->add('success', 'Bol si uspesne odhlaseny.');
    return new RedirectResponse( $app['url_generator']->generate('login') );
})->bind('logout');

$app->post('/upload', function () use ($app) {
    $allowed = array('png', 'jpg', 'zip', 'doc', 'docx', 'pdf', 'ppt', 'pptx', 'c', 'cpp');
    $user = $app['session']->get('user');

    if ( isset($_FILES['upl']) && $_FILES['upl']['error'] == 0 && isset($_POST['zadanie_id']) ) {
        $extension = pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $allowed)) {
            return $app->json(array('status' => 'error'), 500);
        }
        $newPath = substr(hash('sha256', time()), 6, mt_rand(5, 8)).'_'.$_FILES['upl']['name'];
        if (move_uploaded_file($_FILES['upl']['tmp_name'], __DIR__.'/uploads/'.$newPath)) {
            $zadanieId = $_POST['zadanie_id'];
            // cekneme ci je vytvorene zadanie
            $zadanie = $app['db']->fetchAssoc("SELECT id FROM zadania WHERE id = ? AND trieda_id = ? AND ( stav = 2 OR ( stav = 1 AND NOW() <= cas_uzatvorenia ) ) LIMIT 1", array($zadanieId, $user['trieda_id']));

            if (!$zadanie) {
                unlink(__DIR__.'/uploads/'.$newPath);
                throw new Exeption('Neexistujuce zadanie.');
            }

            $odovzdanie = $app['db']->fetchAssoc("SELECT id FROM odovzdania WHERE zadanie_id = ? AND pouzivatel_id = ? LIMIT 1", array($zadanie['id'], $user['id']));
            if (!$odovzdanie) {
                $odovzdanie = array(
                    'pouzivatel_id' => $user['id'],
                    'zadanie_id' => $zadanie['id'],
                    'cas_odovzdania' => new DateTime()
                );

                $app['db']->executeQuery("INSERT INTO odovzdania (pouzivatel_id, zadanie_id, cas_odovzdania) VALUES (?,?,?)", array_values($odovzdanie), array(
                    PDO::PARAM_INT, 
                    PDO::PARAM_INT, 
                    'datetime'
                ));

                $odovzdanie['id'] = $app['db']->lastInsertId();
            } else {
                $app['db']->executeQuery("UPDATE odovzdania SET cas_upravenia = NOW() WHERE id = ?", array($odovzdanie['id']));
            }

            $subor = $app['db']->fetchAssoc("SELECT id, cesta FROM subory WHERE odovzdanie_id = ? AND nazov = ? LIMIT 1", array($odovzdanie['id'], $_FILES['upl']['name']));
            if ($subor) {
                rename(__DIR__.'/uploads/'.$newPath, __DIR__.'/uploads/'.$subor['cesta']); // premazeme stary subor novym
                $app['db']->executeQuery("UPDATE subory SET cas_upravenia = NOW() WHERE id = ?", array($subor['id']));
            } else {
                $app['db']->executeQuery("INSERT INTO subory (odovzdanie_id, nazov, cesta, velkost, cas_odovzdania) VALUES (?,?,?,?,?)", array(
                    $odovzdanie['id'],
                    $_FILES['upl']['name'],
                    $newPath,
                    $_FILES['upl']['size'],
                    new DateTime()
                ), array(PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_STR, PDO::PARAM_INT, 'datetime'));
            }

            return $app->json(array('status' => 'success'), 201);
        }
    }

    return $app->json(array('status' => 'error'), 500);
})->before($checkUser)
->bind('upload');

$app->post('/odovzdaj', function () use ($app) {
    $zadanieId = $_POST['zadanie_id'];
    $user = $app['session']->get('user');

    $zadanie = $app['db']->fetchAssoc("SELECT id FROM zadania WHERE id = ? AND trieda_id = ? AND ( stav = 2 OR ( stav = 1 AND NOW() <= cas_uzatvorenia ) ) LIMIT 1", array($zadanieId, $user['trieda_id']));
    if (!$zadanie) {
        throw new Exception('Neexistujuce zadanie.');
    }

    $odovzdanie = $app['db']->fetchAssoc("SELECT id FROM odovzdania WHERE zadanie_id = ? AND pouzivatel_id = ? LIMIT 1", array($zadanie['id'], $user['id']));
    if (!$odovzdanie) {
        $odovzdanie = array(
            'pouzivatel_id' => $user['id'],
            'zadanie_id' => $zadanie['id'],
            'cas_odovzdania' => new DateTime(),
            'poznamka' => $_POST['poznamka']
        );

        $app['db']->executeQuery("INSERT INTO odovzdania (pouzivatel_id, zadanie_id, cas_odovzdania, poznamka) VALUES (?,?,?,?)", array_values($odovzdanie), array(
            PDO::PARAM_INT, 
            PDO::PARAM_INT, 
            'datetime',
            PDO::PARAM_STR
        ));

        $odovzdanie['id'] = $app['db']->lastInsertId();
    } else {
        $app['db']->executeQuery("UPDATE odovzdania SET cas_upravenia = NOW(), poznamka = ? WHERE id = ?", array($_POST['poznamka'], $odovzdanie['id']));
    }

    $app['session']->getFlashBag()->add('sucess', 'Tvoja odpoved bola zaznamenana.');
    return new RedirectResponse( $app['url_generator']->generate('home') ); 
})->before($checkUser)
->bind('odovzdaj');

$app->get('/login', function () use ($app) {
    $user = new User();
    $form = $app['form.factory']->create(new LoginForm(), $user);
    
    return $app['twig']->render('login.twig', array('form' => $form->createView()));
})->bind('login');

$app->post('/login', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {

    $user = new User();
    $form = $app['form.factory']->create(new LoginForm(), $user);
    
    $form->bind($request);
    if ($form->isValid())
    {
        $loggedUser = $app['login_service']->auth( $user );
        if ( $loggedUser !== FALSE )
        {
            $app['session']->set('user', $loggedUser);
            $app['session']->getFlashBag()->add('success', 'Vitaj spat '.$app->escape($loggedUser['meno']).'.');
            return new RedirectResponse( $app['url_generator']->generate('home') ); 
        } else {
            // login failed
            return new RedirectResponse( $app['url_generator']->generate('login') ); 
        }
    }

    return $app['twig']->render('login.twig', array('form' => $form->createView()));
})->bind('login.process');


/**
 * Sekcia pre ucitelov
 */

$app->post('/zadanie/new', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {
    $user =  $app['session']->get('user');
    
    if ($user['role'] != 2) {
        throw new Exception('Permission denied');
    }
    
    $zadanie = new Zadanie();
    $zadanie->setPouzivatelId( $user['id'] );
    $form = $app['form.factory']->create(new ZadanieForm($app['repository.triedy'], $app['repository.predmety']), $zadanie);
    
    $form->bind($request);
    if ($form->isValid())
    {
        $app['zadania_service']->save( $zadanie );
        $app['session']->getFlashBag()->add('success', 'Zadanie bolo pridane.');
    }
    
    return new RedirectResponse( $app['url_generator']->generate('home') );
})->before($checkUser)->bind('zadanie.new');

$app->get('/zadanie/{id}/delete', function (Silex\Application $app, $id) {
    $user =  $app['session']->get('user');
    if ($user['role'] != 2) {
        throw new Exception('Permission denied');
    }
    
    $app['zadania_service']->delete($id);
    return new RedirectResponse( $app['url_generator']->generate('home') );
})->before($checkUser)->bind('zadanie.delete')->convert('id', function ($id) { return (int) $id; });


$app->get('/zadanie/{id}/zip', function (Silex\Application $app, $id) {
    error_reporting(0);
    $filelist = $app['zadania_service']->getFileList($id);
    
    $stream = function () use ($filelist, $id) {
        $zip = new ZipStream('zadanie_'.$id.'.zip');
        foreach ($filelist as $subor)
        {
            $zip->add_file_from_path('zadanie_'.$id.'/'.$subor['login'].'/'.$subor['nazov'], __DIR__.'/uploads/'.$subor['cesta']);
        }
        $zip->finish();
    };
    
    return $app->stream($stream, 200, array('Content-Type' => 'application/zip'));
})->before($checkUser)->bind('zadanie.zip')->convert('id', function ($id) { return (int) $id; });