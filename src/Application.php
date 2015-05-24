<?php

namespace UserBase\Server;

use Silex\Application as SilexApplication;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\SecurityServiceProvider as SilexSecurityServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\RoutingServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;
use LinkORB\Component\DatabaseManager\DatabaseManager;
use UserBase\Server\Repository\PdoUserRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use RuntimeException;

class Application extends SilexApplication
{
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $this->configureParameters();
        $this->configureService();
        $this->configureRoutes();
        $this->configureTemplateEngine();
        $this->configureSecurity();
    }

    private function configureService()
    {
        /*
        // the form service
        $this->register(new TranslationServiceProvider(), array(
              'locale' => 'en',
              'translation.class_path' =>  __DIR__.'/../vendor/symfony/src',
              'translator.messages' => array(),
        ));
        $this->register(new FormServiceProvider());
        */
        $this->register(new RoutingServiceProvider());
        
        // *** Setup Sessions ***
        $this->register(new \Silex\Provider\SessionServiceProvider(), array(
            'session.storage.save_path' => '/tmp/userbase_sessions'
        ));
        
        $this->register(new SilexSecurityServiceProvider(), array());

        
        $dbname = 'userbase';
        
        $dm = new DatabaseManager();
        $pdo = $dm->getPdo($dbname);
        $this['pdo'] = $pdo;
        
        $factory = $this['security.encoder_factory'];
        $this->userRepository = new PdoUserRepository($pdo, $factory);

    }

    private function configureParameters()
    {
        $parser = new YamlParser();
        $config = $parser->parse(file_get_contents(__DIR__.'/../config.yml'));

        $this['debug'] = true;
        

    }

    private function configureRoutes()
    {
        $locator = new FileLocator(array(__DIR__.'/../app'));
        $loader = new YamlFileLoader($locator);
        $this['routes'] = $loader->load('routes.yml');
    }

    private function configureTemplateEngine()
    {
        $this->register(new TwigServiceProvider(), array(
            'twig.path' => array(
                __DIR__.'/Resources/views/',
            ),
        ));
    }

    private function configureSecurity()
    {

        /*
        $security = $parameters['security'];

        if ($security['encoder']) {
            // $this['security.encoder.digest'] = new PlaintextPasswordEncoder(true);
            $digest = '\\Symfony\\Component\\Security\\Core\\Encoder\\'.$security['encoder'];
            $this['security.encoder.digest'] = new $digest(true);
        }
        */
        
        $this['security.firewalls'] = array(
            'default' => array(
                'anonymous' => true,
                'pattern' => '^/',
                'form' => array('login_path' => '/login', 'check_path' => '/login_check'),
                'logout' => array('logout_path' => '/logout'),
                'users' => $this->getUserRepository(),
            ),
            'api' => array(
                'stateless' => true,
                'pattern' => '^/api',
                'http' => true,
                'users' => $this->getUserRepository(),
            ),
        );
    }

    public function getUserRepository()
    {
        return $this->userRepository;
    }
}
