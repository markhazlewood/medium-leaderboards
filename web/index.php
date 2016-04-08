<?php

require('../vendor/autoload.php');
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

$app->get('/leaderboards', function() use($app)
{
  $clientID = "a84e360b789f";
  $clientSecret = "7a1c2c9af537c000d7cf75a3d0c7cd7ff1d1804a";
  $callback = "https://medium-leaderboards.herokuapp.com/leaderboards/callback";

  $shortTermAuthURL = 'https://medium.com/m/oauth/authorize?client_id=' . $clientID .
                        '&scope=basicProfile,publishPost' .
                        '&state=THISISMARK' .
                        '&response_type=code' .
                        '&redirect_uri=' . $callback;

  return $app->redirect($shortTermAuthURL);

});

$app->get('/leaderboards/callback', function(Request $request) use($app)
{

  return "<pre>" . sizeof($request) . "</pre>";

});


$app->run();
