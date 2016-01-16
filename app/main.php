<?php

use App\Form;
use App\Entity;
use App\Services;
use App\Repository;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

date_default_timezone_set('Europe/Bratislava');

$app = require_once __DIR__ . '/app.php';

/**
 * Middleware for secured views
 * @return RedirectResponse|null null when user is logged in
 */
$checkUser = function () use ($app) {
    $user = $app['session']->get('user');

    if (empty($user)) {
        return new RedirectResponse($app['url_generator']->generate('login'));
    }
};

// Vyber udajov pre zobrazenie podla role prihlaseneho
$app->get('/', function () use ($app) {
    $user = $app['session']->get('user');

    // Zobrazenie pre studenta
    if ($user['role'] == 1) {
        $zadania         = $app[Repository\Entry::class]->getAll();
        $ukonceneZadania = $app[Repository\Entry::class]->getAll(true);

        return $app['twig']->render('home_student.twig', compact('zadania', 'ukonceneZadania'));
    }
    // Zobrazenie pre ucitela
    elseif ($user['role'] == 2) {
        $form = $app['form.factory']->create(Form\Entry::class, new Entity\Entry());
        
        return $app['twig']->render('home_teacher.twig', array(
            'zadaniaZatvorene' => $app[Repository\Entry::class]->getAllForTeacher(true),
            'zadania'          => $app[Repository\Entry::class]->getAllForTeacher(),
            'form'             => $form->createView()
        ));
    }

    return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
})->before($checkUser)
->bind('home');

// Odhlasenie
$app->get('/goodbye', function () use ($app) {
    $app['session']->clear();
    $app['session']->getFlashBag()->add('success', 'Bol si úspešne odhlásený.');
    return new RedirectResponse($app['url_generator']->generate('login'));
})->bind('logout');

// Odovzdavanie suborov
$app->post('/upload', function () use ($app) {
    $allowed = array('png', 'jpg', 'zip', 'doc', 'docx', 'pdf', 'ppt', 'pptx', 'c', 'cpp');
    $user = $app['session']->get('user');

    if (isset($_FILES['upl']) && $_FILES['upl']['error'] == 0 && isset($_POST['zadanie_id'])) {
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

// Odovzdanie celeho zadania
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
    return new RedirectResponse($app['url_generator']->generate('home'));
})->before($checkUser)
->bind('odovzdaj');

/**
 * Prihlasovanie
 */

$app->get('/login', function () use ($app) {
    $user = new User();
    $form = $app['form.factory']->create(Form\Login::class, $user);
    
    return $app['twig']->render('login.twig', array('form' => $form->createView()));
})->bind('login');

$app->post('/login', function (Symfony\Component\HttpFoundation\Request $request) use ($app) {

    $user = new User();
    /** @var \Symfony\Component\Form\Form $form */
    $form = $app['form.factory']->create(Form\Login::class, $user);
    
    $form->handleRequest($request);
    if ($form->isValid()) {
        $loggedUser = $app[Services\LoginService::class]->auth($user);
        if ($loggedUser !== false) {
            $app['session']->set('user', $loggedUser);
            $app['session']->getFlashBag()->add('success', 'Vitaj späť '.$app->escape($loggedUser['meno']).'.');
            return new RedirectResponse($app['url_generator']->generate('home'));
        } else {
            // Prihlasenie zlyhalo
            $app['session']->getFlashBag()->add('error', 'Zadal si nespravne meno alebo heslo.');
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

    $zadanie = new Entity\Entry();
    $zadanie->setPouzivatelId($user['id']);

    /** @var Symfony\Component\Form\Form $form */
    $form = $app['form.factory']->create(Form\Entry::class, $zadanie);

    $form->handleRequest($request);
    if ($form->isValid()) {
        $app[Repository\Entry::class]->save($zadanie);
        $app['session']->getFlashBag()->add('success', 'Zadanie bolo pridane.');
    }

    return new RedirectResponse($app['url_generator']->generate('home'));
})->before($checkUser)->bind('zadanie.new');

$app->get('/zadanie/{id}/delete', function (Silex\Application $app, $id) {
    $user =  $app['session']->get('user');
    if ($user['role'] != 2) {
        throw new Exception('Permission denied');
    }

    $app[Repository\Entry::class]->delete($id);
    return new RedirectResponse($app['url_generator']->generate('home'));
})->before($checkUser)->bind('zadanie.delete')->convert('id', function ($id) { return (int) $id; });

/**
 * Stahovanie zadani
 */

$app->get('/zadanie/{id}/zip', function (Silex\Application $app, $id) {
    error_reporting(0);
    $filelist = $app[Repository\Entry::class]->getFileList($id);
    $notelist = $app[Repository\Entry::class]->getNotes($id);
    
    $stream = function () use ($filelist, $notelist, $id) {
        $zip = new ZipStream\ZipStream('zadanie_' . $id . '.zip');
        foreach ($notelist as $note) {
            if (empty($note['poznamka'])) {
                continue;
            }
            $zip->addFile('zadanie_' . $id . '/' . $note['login'] . '/poznamka.txt', $note['poznamka']);
        }
        foreach ($filelist as $subor) {
            $zip->addFileFromPath('zadanie_' . $id . '/' . $subor['login'] . '/' . $subor['nazov'],
                __DIR__ . '/uploads/' . $subor['cesta']);
        }
        $zip->finish();
    };
    
    return $app->stream($stream, 200, array('Content-Type' => 'application/zip'));
})->before($checkUser)->bind('zadanie.zip')->convert('id', function ($id) { return (int) $id; });

$app->get('/component/student-ended-terms',
    function (Silex\Application $app, Symfony\Component\HttpFoundation\Request $request) {

        /** @var Repository\Entry $entryRepository */
        $entryRepository = $app[Repository\Entry::class];

        $start = $request->get('start');
        $step  = -50;

        if ($start < 0) {
            $start = abs($start);
        } else {
            $step = abs($step);
        }

        $from = $start + $step;

        return $app['twig']->render('components/student_ended_terms.twig', [
            'from'   => $from,
            'to'     => $from + abs($step),
            'target' => 'student-ended-terms',
            'terms'  => $entryRepository->getAll(true)
        ]);
    })->before($checkUser);
