<?php
namespace NinjaSentry\Sai;

/**
 * SAI Prototype
 * --------------------
 * Router Class
 *
 * ninjasentry.com 2016
 */

use NinjaSentry\Katana\Http\Status;

/**
 * "Any intelligent fool can make things bigger, more complex, and more violent.
 * It takes a touch of genius -- and a lot of courage -- to move in the opposite direction."
 * ~ Albert Einstein
 */

/**
 * Class Router
 * @package NinjaSentry\Sai
 */
class Router
{
    /**
     * Default application route
     * @var string
     */
    public $default_handler = '';

    /**
     * @var string
     */
    public $error_handler = 'not_found';

    /**
     * @var
     */
    public $module;

    /**
     * @var
     */
    public $controller;

    /**
     * @var
     */
    public $handler;

    /**
     * @var
     */
    public $action;

    /**
     * @var
     */
    public $param;

    /**
     * @var
     */
    public $route;

    /**
     * @var
     */
    public $map;

    /**
     * Routes Map
     * @var array
     */
    private $routes = [
        'get'  => [],
        'post' => []
    ];

    /**
     * Router constructor.
     * Set default application route
     */
    public function __construct(){
        $this->default_handler = Route::DEFAULT_MODULE . '/' . Route::DEFAULT_CONTROLLER;
    }

    /**
     * Add Routes based on Route method
     * @param array $routes
     * @throws \Exception
     */
    public function add( $routes = [] )
    {
        if( ! is_array( $routes ) || empty( $routes ) )
        {
            throw new \Exception(
                'Router Error : Route Map import failed - No rules found in app/config/routes.php '
            );
        }

        // todo :: cache resolved routes instead
        // $this->map = $routes;

        /**
         * Short Route policy syntax
         * GET@module/controller/action
         */
        foreach( $routes as $route_key => $route )
        {
            if( ! empty( $route ) && is_string( $route ) )
            {
                if( strpos( $route, '@' ) !== false )
                {
                    $path  = $route; // clone route
                    $route = [];     // reset route

                    list( $method, $handler )    = explode( '@' , $path );
                    list( $module, $controller ) = explode( '::', $handler );

                    $route['method']     = $method;
                    $route['module']     = $module;
                    $route['controller'] = $controller;
                }
            }

            // Route Identifier
            $name = $route_key;

            // Request Method : ( get | post )
            $method = $route['method'];

            // Module - top level controller dir
            $module = $route['module'];

            // Controller - sub level controller dir
            $controller = $route['controller'];

            $action = '';
            if( isset( $route['action'] ) ) {
                $action = $route['action'];
            }

            $param = '';
            if( isset( $route['param'] ) ) {
                $param = $route['param'];
            }

            $arg = '';
            if( isset( $route['arg'] ) ) {
                $arg = $route['arg'];
            }

            // uri : /category/php
            $path = $module . '/' . $controller;

            // uri : /category/php/frameworks
            if( ! empty( $action ) ) {
                $path .= '/' . $action;
            }

            // uri : /bridge/firewall/ip/edit
            if( ! empty( $param ) ) {
                $path .=  '/' . $param;
            }

            // uri : /bridge/firewall/ip/edit/1
            if( ! empty( $arg ) ) {
                $path .=  '/' . $arg;
            }

            /**
             * Assign Route by request method
             * get | about = about( controller )
             */
            $this->routes[ $method ][ $name ] = trim( $path );
        }
    }

    /**
     * Find a matching Route for client request
     * @param Route $route
     * @return array|bool
     * @throws \Exception
     */
    public function match( Route $route )
    {
        /**
         * Assign route
         */
        $this->route = $route;

        /**
         * Client Request Method
         */
        $method = mb_strtolower( $route->getMethod() );

        /**
         * SlashGuard - Slash Overload Interception
         * Redirect /git///sentrycms////account//////dashboard
         * to /git/sentrycms/account/dashboard
         */
        $this->preventDoubleSlashes();

        /**
         * Duplicate content prevention
         * No public access to /index routes
         *
         * Prevent 'index' to be used as the last segment in ANY route
         *
         * Examples:
         * On direct access to site.com/contact/index, redirect client to site.com/contact
         * On direct access to site.com/index, redirect to site.com ( site root )
         */
        $this->preventIndexRoute();

        /**
         * Duplicate content prevention
         * Intercept uppercase route requests
         * 301 perm redirect to lowercase version
         */
        $this->preventUppercaseDuplicate();

        /**
         * Duplicate content prevention
         * Prevent trailing slash at the end of routes
         */
        $this->preventTrailingSlash();

        /**
         * Default Route Match
         * Intercept requests for site root path /
         * Get component -> set default handler / skip route match
         * $default_handler = 'home/index';
         */
        if( $this->route->controller === Route::DEFAULT_CONTROLLER
            && $this->route->action === Route::DEFAULT_ACTION
        ){
            $component = $this->getComponent();
            return $component;
        }

        /**
         * Foreach line counter
         */
        $line = 0;

        /**
         * Find matching route - build & return route component array
         * Loop through route pattern map to find a match for the ( input ) route requested by client
         */
        foreach( $this->routes[ $method ] as $pattern => $handler )
        {
            ++$line;

            /**
             * Direct match on module route ( first route segment )
             * eg : $pattern === 'about' && $module === 'about'
             */
            if( $pattern === $this->route->module ) {
                $component = $this->getComponent( $handler );
                return $component;
            }

            /**
             * Standard Match ( Non regex route match )
             * eg: login/attempt, category/news
             */
            if( strpos( $pattern, '/' ) !== false && strpos( $pattern, ':' ) === false )
            {
                $paths   = explode( '/', $pattern );
                $handles = explode( '/', $handler );

                /**
                 * No handles = no matches
                 */
                if( ! isset( $handles[0] ) ) break;

                /**
                 * Match handles
                 */
                if( $handles[0] === $this->route->module )
                {
                    if( isset( $handles[1] ) ) $handles[1] = $paths[1];
                    if( isset( $handles[2] ) ) $handles[2] = $paths[2];
                    if( isset( $handles[3] ) ) $handles[3] = $paths[3];
                    if( isset( $handles[4] ) ) $handles[4] = $paths[4];

                    $handler   = implode( '/', $handles );
                    $component = $this->getComponent( $handler );
                    return $component;
                }
            }

            /**
             * Non Regex 'module route' match
             * examples:
             * 'php-firewall/:controller/:action'
             * /category/php/router
             * Break pattern handler into segments
             * match route module with first segment of pattern
             */
            if( strpos( $pattern, '/:' ) !== false )
            {
                $parts = explode( '/', $pattern );

                if( $this->route->module === $parts[0] )
                {
                    // replace :controller
                    if( isset( $parts[1] ) ) {
                        $handler = str_replace( '1', $this->route->controller, $handler );
                    }

                    // replace :action
                    if( isset( $parts[2] ) ) {
                        $handler = str_replace( '2', $this->route->action, $handler );
                    }

                    // replace :param
                    if( isset( $parts[3] ) ) {
                        $handler = str_replace( '3', $this->route->param, $handler );
                    }

                    // replace :args
                    if( isset( $parts[4] ) ) {
                        $handler = str_replace( '4', $this->route->arg, $handler );
                    }

                    $component = $this->getComponent( $handler );
                    return $component;
                }
            }

            /**
             * Regex Pattern route match : <controller><action><param><arg>
             *
             * uri format  : /module/controller/action/param/arg
             * uri request : /phalcon-php-framework
             * uri pattern : (?P<action>[a-z]+)-php-(?P<controller>[a-z]+) | ( <:action>-php-<:controller> )
             * uri handler : php/2/1
             * uri rewrite : php/framework/phalcon
             *
             */
            if( strpos( $pattern, '<' ) !== false )
            {
                // token replacement
                // <:controller> = (?P<controller>[a-z]+)
                // <:action>     = (?P<action>[a-z]+)
                // $pattern      = $this->regexTokens( $pattern );

                if( preg_match( '#' . $pattern . '#', $this->route->module, $matched  ) )
                {
                    $handles = explode( '/', $handler );

                    if( isset( $handles[1] )) {
                        $handles[1] = $matched['controller'];
                    }

                    if( isset( $handles[2] )) {
                        $handles[2] = $matched['action'];
                    }

                    if( isset( $handles[3] )) {
                        $handles[3] = $matched['param'];
                    }

                    if( isset( $handles[4] )) {
                        $handles[4] = $matched['arg'];
                    }

                    $handler = implode( '/', $handles );

                    $component = $this->getComponent( $handler );
                    return $component;
                }
            }
        }

        return false;
    }

    /**
     * @param string $key
     * @return mixed|string
     */
    public function regexTokens( $key = '' )
    {
        if( strpos( $key, '<:controller>' ) !== false )  {
            $key = str_replace( '<:controller>', '(?P<controller>[a-z]+)', $key );
        }

        if( strpos( $key, '<:action>' ) !== false ) {
            $key = str_replace( '<:action>', '(?P<action>[a-z]+)', $key );
        }

        if( strpos( $key, '<:param>' ) !== false ) {
            $key = str_replace( '<:param>', '(?P<param>[a-z]+)', $key );
        }

        return $key;
    }

    /**
     * Get Component
     * @param string $handler
     * @return array
     * @throws \Exception
     */
    public function getComponent( $handler = '' )
    {
        if( empty( $handler ) ) {
            $handler = $this->default_handler;
        }

        /**
         * Split Handler pattern string
         */
        $parts = explode( '/', $handler );

        /**
         * Exit if no component is available
         */
        if( ! isset( $parts[0] ) || empty( $parts[0] ) )
        {
            throw new \Exception(
                'Router Error :: GetComponent ( No module segment found )'
            );
        }

        /**
         * Prepare module assignment
         */
        $module = $parts[0];

        /**
         * Prepare Controller
         */
        if( ! isset( $parts[1] ) ) {
            $controller = Route::DEFAULT_CONTROLLER;
        } else {
            $controller = $parts[1];
        }

        /**
         * Prepare action
         */
        if( ! isset( $parts[2] ) || empty( $parts[2] ) ) {
            $action = Route::DEFAULT_ACTION;
        } else {
            $action = $parts[2];
        }

        /**
         * Prepare param
         * No default param
         */
        $param = '';
        if( isset( $parts[3] ) && ! empty( $parts[3] ) ) {
            $param = $parts[3];
        }

        /**
         * Assign component parts
         */
        $this->module     = $module;
        $this->controller = $controller;
        $this->action     = $action;
        $this->param      = $param;

        /**
         * Build Component
         */
        $component = [
            'handler'        => $handler,
            'module'         => $this->module,
            'controller'     => $this->getController(),
            'action'         => $this->getAction(),
            'param'          => $this->param,
        ];

        return $component;
    }

    /**
     * @return string
     */
    public function getModule() {
        return $this->module;
    }

    /**
     * Prepare Controller class name
     * @return string
     * @throws \Exception
     */
    public function getController()
    {
        /**
         * Base Controller Name
         * /module/controller
         */
        $controllerName = 'App\\Controller\\'
            . ucfirst( $this->module )     . '\\'
            . ucfirst( $this->controller ) . 'Controller';

        /**
         * Direct Route Controller
         * -----------------------
         * Module based controller / Single segment route
         * eg: http://cms.local/phalcon-php-framework
         */
        if( $this->route->controller === 'index' || $this->action === 'index' )
        {
            /**
             * Controller Based Controller Name
             * /module/controller
             */
            if( ! class_exists( $controllerName ) )
            {
                // Check for /module/controller/action
                // eg : /Category/CentosController/IndexAction

                $controllerName = 'App\\Controller\\'
                    . ucfirst( $this->module )     . '\\'
                    . ucfirst( $this->controller ) . '\\IndexController';

                if( ! class_exists( $controllerName ) )
                {
                    throw new \Exception(
                        'Router Error :: No Index controller class exists for ( ' . $controllerName . ' )'
                        , Status::NOT_FOUND
                    );
                }
            }

            return $controllerName;
        }

        /**
         * Action Based Controller Name
         */
        if( ! empty( $this->action ) )
        {
            /**
             * Build Action Controller file path
             * \App\Controller\MODULE\CONTROLLER\ACTION\
             * \App\Controller\Category\Php\FrameworkController
             * \App\Controller\Bridge\Firewall\HostController
             */
            if( ! method_exists( $controllerName, '__call' ) )
            {
                $controllerName = 'App\\Controller\\'
                    . ucfirst( $this->module )     . '\\'
                    . ucfirst( $this->controller ) . '\\'
                    . ucfirst( $this->action )     . 'Controller';

                /**
                 * Rewrite action back to default controller method
                 * Used by dispatcher action method
                 */
                $this->action = Route::DEFAULT_ACTION;
            }

            if( ! class_exists( $controllerName ) )
            {
                throw new \Exception(
                    'Router Error :: No Action controller class exists for ( ' . $controllerName . ' )'
                    , Status::NOT_FOUND
                );
            }

            return $controllerName;
        }

        /**
         * Module Index Controller
         * Top level Module controller
         */
        $controllerName = 'App\\Controller\\'
            . ucfirst( $this->module )
            . '\\IndexController';

        /**
         * Default index controller
         * last attempt to find a match
         * abort if no controller class exists
         */
        if( ! class_exists( $controllerName ) )
        {
            throw new \Exception(
                'Router Error :: Final Match - No Module Index controller class exists for ( ' . $controllerName . ' )'
            );
        }

        return $controllerName;
    }

    /**
     * Prepare Action name ( eg ContactAction )
     * @return string
     */
    public function getAction(){
        $action = str_replace( [ '-', '_', '.' ], '', $this->action ) . 'Action';
        return $action;
    }

    /**
     * Search Routes Map
     * @param $method
     * @param $name
     * @return mixed
     */
    public function getKey( $method, $name ) {
        return array_search( $name, $this->routes[ $method ] );
    }

    /**
     * Module Controller
     * @return bool|string
     * @throws \Exception
     */
    public function moduleController()
    {
        if( isset( $this->module ) )
        {
            /**
             * Prepare top level controller path
             * \App\Controller\CategoryController
             */
            $controllerName = 'App\\Controller\\' . ucfirst( $this->module ) . 'Controller';

            /**
             * Default index controller
             * last attempt to find a match
             */
            if( ! class_exists( $controllerName ) )
            {
                throw new \Exception(
                    'Router Error :: No Module controller class exists for ( ' . $controllerName . ' )'
                );
            }

            return $controllerName;
        }

        return false;
    }

    /**
     * Duplicate Content Prevention
     * Prevent direct access to controller/action index method
     * Redirect to fixed route
     */
    public function preventIndexRoute()
    {
        $request = $this->route->request;

        /**
         * Prevent direct access to site.com/index
         * Permanent redirect to site.com root
         * eg : ( IndexController/indexAction )
         */
        if( $request === 'index' )
        {
            $destination = $this->route->http_path;
            header( 'Location: ' . $destination, true, 301 );
            exit;
        }

        /**
         * Prevent direct access to site.com/page/index
         * permanent redirect to site.com/page
         */
        if( preg_match( '#\/index$#DUi', $request ))
        {
            $len         = strlen('/index');
            $new_uri     = mb_substr( $request, 0, -$len );
            $destination = $this->route->http_path . '/' . $new_uri;
            header( 'Location: ' . $destination, true, 301 );
            exit;
        }

        /**
         * Prevent direct access to ( site.com/index.php )
         * redirect to site root ( site.com )
         */
        if( ! empty( $_SERVER['REDIRECT_URL'] ) )
        {
            $request = trim( $_SERVER['REDIRECT_URL'] );

            if( preg_match( '#\/index\.php#Ui', $request ) ) {
                $destination = $this->route->http_path;
                header( 'Location: ' . $destination, true, 301 );
                exit;
            }
        }

        /*
        if( ! empty( $_SERVER['REQUEST_URI'] ) )
        {
            $request = $_SERVER['REQUEST_URI'];

            if( preg_match( '#\/index\.php#Ui', $request ) )
            {
                $len         = strlen('/index.php');
                $new_uri     = mb_substr( $uri, 0, -$len );
                $destination = $this->route->base_path . '/' . $new_uri;
                header( 'Location: ' . $destination, true, 301 );
                exit;

            }
        }
        */
    }

    /**
     * Prevent duplicate content urls
     * 301 redirect route uri with a trailing slash
     * ( redirect to the non trailing slash version )
     * @return bool
     */
    public function preventTrailingSlash()
    {
        $request = $this->route->request;

        if( ! empty( $request ) )
        {
            if( mb_substr( $request, -1 ) === '/' )
            {
                $path = $this->route->http_path;
                if( mb_substr( $path, -1 ) !== '/' ) {
                    $path .= '/';
                }

                $destination = $path . rtrim( $request, '/' );

                header( 'Location: ' . $destination, true, 301 );
                exit;
            }
        }
    }

    /**
     * Intercept Uppercase page requests
     * -> 301 redirect to lowercase equivalent
     */
    public function preventUppercaseDuplicate()
    {
        $path    = $this->route->http_path;
        $request = $this->route->request;

        if( ! empty( $request ) )
        {
            $destination = $path . '/' . mb_strtolower( $request );

            if( preg_match('#[A-Z]+#U', $request ) ) {
                header( 'Location: ' . $destination, true, 301 );
                exit;
            }
        }
    }

    /**
     * SlashGuard
     * Prevent double slashes ( or more ) in ANY part of the URI
     */
    public function preventDoubleSlashes()
    {
        if( ! empty( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) )
        {
            $uri = trim( $_SERVER['REQUEST_URI'] );

            if( preg_match( '#[\/]{2,}#', $uri ) )
            {
                // build new destination route ( absolute url )
                $destination = $this->route->http_path . $this->route->request;

                // redirect to final destination
                header( 'Location: ' . $destination, true, 301 );
                exit;
            }
        }
    }
}