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
// $app->get('/db/', function() use($app)
// {
//   $st = $app['pdo']->prepare('SELECT name FROM test_table');
//   $st->execute();
//
//   $names = array();
//   while ($row = $st->fetch(PDO::FETCH_ASSOC))
//   {
//     $app['monolog']->addDebug('Row ' . $row['name']);
//     $names[] = $row;
//   }
//
//   return $app['twig']->render('database.twig', array( 'names' => $names ));
//
// });

$app->get('/leaderboards/login', function() use($app)
{
  $clientID = "a84e360b789f";
  $clientSecret = getenv('MEDIUM_APP_CLIENT_SECRET');
  $callback = "https://medium-leaderboards.herokuapp.com/leaderboards/medium-callback";

  $shortTermAuthURL = 'https://medium.com/m/oauth/authorize?client_id=' . $clientID .
                        '&scope=basicProfile,publishPost' .
                        '&state=THISISMARK' .
                        '&response_type=code' .
                        '&redirect_uri=' . $callback;

  return $app->redirect($shortTermAuthURL);

});

$app->get('/leaderboards/medium-callback', function(Request $request) use($app)
{
  // Check if we need to parse the response as JSON
  if (0 === strpos($request->headers->get('Content-Type'), 'application/json'))
  {
    $responseData = json_decode($request->getContent(), true);


  }
  else
  {
    // Check if this is the initial login response
    $error = $request->query->get('error', "none");
    $state = $request->query->get('state', "none");
    $code = $request->query->get('code', "none");

    // Check for and handle error
    if ($state === "none")
    {
      return "<pre>Error: " . $error . "</pre>";
    }

    $callback  = "https://medium-leaderboards.herokuapp.com/leaderboards/medium-callback";

    // Get long-term auth for the user
    $postData = '' .
      'code=' . $code .
      '&client_id=a84e360b789f' .
      '&client_secret=' . getenv('MEDIUM_APP_CLIENT_SECRET') .
      '&grant_type=authorization_code' .
      '&redirect_uri=' . $callback;

      $postRequest = 'https://api.medium.com?' . $postData;

      $app['monolog']->addDebug($postRequest);

      return $app->redirect($postRequest);
  }

  return "nothing";
});


$app->run();
