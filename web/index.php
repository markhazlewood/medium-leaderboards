<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Connect to Postgres DB using Herrera PDO
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
                array(
                  'pdo.dsn' =>      'pgsql:dbname=' . ltrim($dbopts["path"], '/') .
                                    ';host=' . $dbopts["host"] .
                                    ';port=' . $dbopts["port"],
                  'pdo.username' => $dbopts["user"],
                  'pdo.password' => $dbopts["pass"]
                )
              );

// Our web handlers

// root
$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.twig');
});

// /cowsay
$app->get('/cowsay', function() use($app)
{
  $app['monolog']->addDebug('cowsay');
  return "<pre>" . \Cowsayphp\Cow::say("Cool beans") . "</pre>";
});

// /db
$app->get('/db/', function() use($app)
{
  $st = $app['pdo']->prepare('SELECT name FROM test_table');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC))
  {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('database.twig', array( 'names' => $names ));

});


$app->run();
