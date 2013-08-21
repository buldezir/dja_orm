<?php
define('APP_ENV', ($_SERVER['SERVER_NAME'] == 'localhost' ? 'dev' : 'prod'));
define('DJA_PATH', realpath(__DIR__ . '/../src'));
define('DJA_APP_PATH', realpath(__DIR__));
/** @var $loader \Composer\Autoload\ClassLoader */
$loader = require_once __DIR__ . '/../vendor/autoload.php'; // change it!
$loader->add('Dja', DJA_PATH);
$loader->loadClass('Dja\\Util\\Functions');

spl_autoload_register('import', true);


use Dja\Application\Application, Dja\Application\SecureRoute;
use Dja\Auth\Acl;

SecureRoute::setReverseControllerCallback(function ($v) {
    return controller_reverse($v);
});

$app = Application::getInstance();
$conn = new Dja\Db\Pdo("pgsql:host=localhost;dbname=test;port=6432", 'test', 'pw');
$conn->setAsDefault();
$app['pdo_db'] = $conn;

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => DJA_APP_PATH . '/templates'
));
/**
    CREATE TABLE "public"."users" (
    "user_id" serial,
    "email" varchar(64) NOT NULL COLLATE "default",
    "password" varchar(128) NOT NULL COLLATE "default",
    "full_name" varchar(255) COLLATE "default",
    "role_id" int2 NOT NULL,
    "date_added" timestamp(6) NOT NULL DEFAULT now(),
    "date_updated" timestamp(6) NOT NULL DEFAULT now(),
    "is_active" bool NOT NULL DEFAULT false,
    "timezone" varchar(32) NOT NULL DEFAULT 'Europe/Moscow'::character varying COLLATE "default",
    CONSTRAINT "users_pkey" PRIMARY KEY ("user_id") NOT DEFERRABLE INITIALLY IMMEDIATE,
    CONSTRAINT "email" UNIQUE ("email") NOT DEFERRABLE INITIALLY IMMEDIATE
    )
    WITH (OIDS=FALSE);
    CREATE UNIQUE INDEX  "email" ON "public"."users" USING btree(email COLLATE "default" ASC NULLS LAST);
 */
$app->register(new Dja\Auth\Provider());

Acl::adminGodMode();

$app->get('/', controller('index/index'))->allow(Acl::ALL);

$app->match('/sign-in/', controller('index/signin'))->allow(Acl::ANONYMOUS);
$app->match('/sign-up/', controller('index/signup'))->allow(Acl::ANONYMOUS);
$app->match('/logout/', controller('index/logout'))->allow(Acl::ALL)->deny(Acl::ANONYMOUS);

$app->error(function (\Exception $e, $code) use ($app) {
    if ($e->getCode() !== 0) {
        $code = $e->getCode();
    }
    $desc = null;
    switch ($code) {
        case 404:
            $message = 'The requested page could not be found.';
            break;
        case 403:
            $message = $e->getMessage();
            $desc = 'You must sign-in or have access rights to view this page.';
            break;
        default:
            $message = 'We are sorry, but something went terribly wrong.';
    }

    if ($app['debug']) {
        $debug = new Symfony\Component\HttpKernel\Debug\ExceptionHandler();
        $exception = Symfony\Component\HttpKernel\Exception\FlattenException::create($e);
        $traceHtml = $debug->getContent($exception);
        $traceCss = $debug->getStylesheet($exception);
        $trace = '<style type="text/css">'.$traceCss.' .sf-reset h1{ display:none!important; }</style>'.$traceHtml;
    } else {
        $trace = '';
    }

    return $app->render('error.twig', array('error' => $message, 'desc' => $desc, 'trace' => $trace));
});

$app->run();


