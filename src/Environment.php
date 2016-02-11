<?php
namespace NinjaSentry\Sai;

/**
 * SAI Prototype
 * -----------------------
 * ninjasentry.com 2016
 */

/**
 * Class Environment
 * @package NinjaSentry\Sai
 */
class Environment
{
    /**
     * Production Mode Key
     */
    const PRODUCTION_MODE = 'production';

    /**
     * Development Mode Key
     */
    const DEVELOPMENT_MODE = 'development';

    /**
     * Staging Mode Key
     */
    const STAGING_MODE = 'staging';

    /**
     * Auto detect development mode
     * eg : '`^(?:10\.|127\.0\.0\.1|172\.(?:1[6-9|2[0-9]|3[01])|192\.168\.)`';
     *
     * * moved to config file *
     */
    const DEVELOPMENT_SERVER_IP_PATTERN = '';

    /**
     * Validated domain / hostname
     *
     * @var string
     */
    private $domain = '';

    /**
     * Application environment mode state
     *
     * @var string
     */
    public $mode = '';

    /**
     * Accepted Host names
     *
     * @var
     */
    public $hosts;

    /**
     * Application settings
     *
     * @var
     */
    private $settings;

    /**
     * Environment constructor.
     *
     * @param \NinjaSentry\Sai\Config $config
     */
    public function __construct( Config $config )
    {
        /**
         * Load config object
         */
        $this->config = $config;

        /**
         * Load application settings
         */
        $this->settings();

        /**
         * Prepare application for launch
         */
        $this->prepare();
    }

    /**
     * Get Application Environment mode
     * Production (default) | Development | Staging
     * @return string
     */
    public function getMode(){
        return $this->mode;
    }

    /**
     * Application Http Path
     * @param bool|false $ssl
     * @return string
     */
    public function httpPath( $ssl = false )
    {
        $protocol = empty( $ssl )
            ? 'http'
            : 'https';

        $httpPath  = $protocol . '://' . $this->domain;
        $httpPath .= $this->appRoot();

        if( substr( $httpPath, -1 ) !== '/' ) {
            $httpPath .= '/';
        }

        return $httpPath;
    }

    /**
     * Load application config settings
     */
    private function settings()
    {
        /**
         * Load hosts from config file
         * eg : ( set in app/config/env.php )
         */
        $this->hosts = (array) $this->config->get('hosts');

        /**
         * Get host name from Client request
         */
        $this->domain = $this->getHostName();

        /**
         * Get environment mode
         */
        $this->mode = $this->getModeFromHost();

        /**
         * Load settings based on env mode
         */
        $this->settings = $this->config->get( $this->mode );

        /**
         * validate server name / http host
         */
        $this->validateHostName();
    }

    /**
     * Prepare Environment
     * Set Secure Foundation
     * Set TimeZone
     */
    private function prepare()
    {
        /**
         * Set Application TimeZone
         */
        $this->setTimezone();

        /**
         * Set Application foundation settings based on Environment
         */
        $this->secureFoundation();

        /**
         * Set error / exception / shutdown handler
         */
        set_error_handler( 'sai_error_handler' );
        set_exception_handler( 'sai_exception_handler' );
        register_shutdown_function( 'sai_shutdown_handler' );
    }

    /**
     * Set TimeZone
     * Use config->timezone or default timezone
     * Eg : date_default_timezone_set( 'UTC' );
     * http://php.net/manual/en/timezones.php
     */
    private function setTimezone()
    {
        $tz = $this->settings->timezone;

        if( ! empty( $tz ) && is_string( $tz ) ) {
            date_default_timezone_set( $tz );
        } else {
            date_default_timezone_set( date_default_timezone_get() );
        }
    }

    /**
     * Secure Environment
     * Set Error Reporting based on environment
     * Prevent information disclosure by not displaying errors in production mode
     * Set Error Logging
     * Log all application errors to file
     * http://php.net/manual/en/errorfunc.configuration.php
     */
    private function secureFoundation()
    {
        /**
         * Set global error reporting level
         */
        error_reporting( E_ALL );

        /**
         * Display Errors
         */
        switch( $this->mode )
        {
            default:
            case self::PRODUCTION_MODE:
                ini_set( 'display_errors', false );
                ini_set( 'display_startup_errors', false );
                break;

            case self::DEVELOPMENT_MODE:
            case self::STAGING_MODE:
                ini_set( 'display_errors', true );
                ini_set( 'display_startup_errors', true );
                break;
        }

        /**
         * No html errors. alright
         */
        ini_set( 'html_errors', false );

        /**
         * Log all errors
         * TODO : ensure log file path is set above web root
         * set default location : /var/log/ ( vHost ServerName ) /
         */
        ini_set( 'log_errors', true );
        ini_set( 'error_log', $this->settings->error_log ); // ..app_error.log

        /**
         * Mail Log
         */
        // Log all mail() calls including the full path of the script, line #, to address and headers
        // ini_set( 'mail.log', 'php_mail.log' );

        /**
         * Turn off session auto_start if enabled
         */
        if( ini_get( 'session.auto_start' ) !== 0 ) {
            ini_set( 'session.auto_start', 0 );
        }

        /**
         * Set a maximum Memory Limit
         * $this->settings->memory_limit
         */
        ini_set( 'memory_limit', $this->settings->memory_limit );

        /**
         * Set maximum execution time [ Default : 30 - 60 seconds ]
         * http://php.net/manual/en/info.configuration.php#ini.max-execution-time
         */
        //if( isset( $this->config->settings->max_exec_time ) ) {
        // ini_set( 'max_execution_time', $this->config->settings->max_exec_time );
        //} else {
        // set a default max execution time
        // uses default php ini setting
        // ini_set( 'max_execution_time', '42' );
        // }

        /**
         * upload_max_filesize
         */

        /**
         * post_max_size
         */

        /**
         * Max_input_time [ Default : -1 ]
         * http://php.net/manual/en/info.configuration.php#ini.max-input-time
         */
        // ini_set( 'max_input_time', '' );

        /**
         * Max input nesting level [ Default : 64 ]
         * http://php.net/manual/en/info.configuration.php#ini.max-input-nesting-level
         */
        // ini_set( 'max_input_nesting_level', '' );

        /**
         * Max input vars [ Default : 1000 ]
         * http://php.net/manual/en/info.configuration.php#ini.max-input-vars
         */
        // ini_set( 'max_input_vars', '' );

    }

    /**
     * Todo :: validation - use firewall input filter on all server input ?
     */
    private function validateHostName()
    {
        $validHost  = false;
        $serverName = $this->settings->server_name;

        if( ! empty( $this->domain ) && is_string( $this->domain ) )
        {
            if( $this->domain !== $serverName )
            {
                throw new \RuntimeException(
                    'Environment Error :: Unknown host name requested'
                );
            }

            $validHost = $this->domain;
        }

        if( empty( $validHost ) )
        {
            throw new \RuntimeException(
                'Environment Error :: Server Name not found'
            );
        }

        return $validHost;
    }

    /**
     * Todo :: validation - use firewall input filter on all server input ?
     *
     * @return bool|string
     */
    private function getHostName()
    {
        $validHost = false;

        $serverName = ( ! empty( $_SERVER['SERVER_NAME'] )
            ? $_SERVER['SERVER_NAME']
            : ''
        );

        $httpHost = ( ! empty( $_SERVER['HTTP_HOST'] )
            ? $_SERVER['HTTP_HOST']
            : ''
        );

        if( ! empty( $this->hosts ) && is_array( $this->hosts ) )
        {
            foreach( $this->hosts as $envMode => $hostNames )
            {
                if( empty( $hostNames ) ) {
                    continue;
                }

                if( in_array( $serverName, $hostNames ) ) {
                    $validHost = $serverName;
                }
                elseif( in_array( $httpHost, $hostNames ) ) {
                    $validHost = $httpHost;
                }
            }
        }

        if( empty( $validHost ) )
        {
            throw new \RuntimeException(
                'Environment Error :: Client requested unknown Server Name / Http Host'
            );
        }

        return $validHost;
    }

    /**
     * Auto Detect Server Environment ( Overrides the default Production Environment mode )
     * Host Match : Development server Host Name Match ( development | staging | production )
     * IP Match : A positive match for a Private / Reserved etc IPv4 address automatically sets app to development mode
     *
     * @return string
     */
    private function getModeFromHost()
    {
        /**
         * Default to most restricted environment mode
         */
        $mode = self::PRODUCTION_MODE;

        /**
         * Host pre-validation state
         */
        $validated = false;

        /**
         * Override default mode
         * Find matching Host or IP
         */
        if( empty( $this->hosts ) ) {
            $validated = $this->matchIp();
        }
        elseif( is_array( $this->hosts ) ) {
            $validated = $this->matchHost();
        }

        /**
         * Set mode
         */
        if( $validated ) {
            $mode = $validated;
        }

        return $mode;
    }

    /**
     * Detect environment based on Hostname
     * Default to Production Mode
     */
    private function matchHost()
    {
        /**
         * Match Development Server Hosts
         */
        if( isset( $this->hosts['development'] ) && is_array( $this->hosts['development'] ) )
        {
            if( in_array( $this->domain, $this->hosts['development'] ) ) {
                return self::DEVELOPMENT_MODE;
            }
        }

        /**
         * Match Staging Server Hosts
         */
        if( isset( $this->hosts['staging'] ) && is_array( $this->hosts['staging'] ) )
        {
            if( in_array( $this->domain, $this->hosts['staging'] ) ) {
                return self::STAGING_MODE;
            }
        }

        return false;
    }

    /**
     * Detect environment based on server IP address ( IPv4 Only )
     * TODO : load a user provided policy file to auto set environments via specific IP ranges
     */
    private function matchIp()
    {
        $devIp = self::DEVELOPMENT_SERVER_IP_PATTERN;

        if( ! empty( $devIp ) && ! empty( $_SERVER['SERVER_ADDR'] ) )
        {
            if( $ip = ( filter_var( $_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) )
            {
                if( preg_match( $devIp, $ip ) ) {
                    return self::DEVELOPMENT_MODE;
                }
            }
        }

        return false;
    }

    /**
     * Get application root path set in app/config/env.php
     * Used for building internal application path
     *
     * @return mixed
     * @throws \Exception
     */
    private function documentRoot()
    {
        if( empty( $this->settings->document_root ) )
        {
            throw new \Exception(
                'Environment Error :: Expected document root setting was not found in env config'
            );
        }

        return $this->settings->document_root;
    }

    /**
     * Application base path for building a HTTP path
     *
     * @return mixed
     * @throws \Exception
     */
    private function appRoot()
    {
        if( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
            $basePath = $_SERVER['DOCUMENT_ROOT'];
        } else {
            // todo :: calculate sub directory depth
            $basePath = dirname( dirname( $_SERVER['SCRIPT_NAME'] ) );
        }

        if( empty( $basePath ) )
        {
            throw new \Exception(
                'Environment Error :: Document root was not found'
            );
        }

        $path = str_replace( $basePath, '', $this->documentRoot() );

        return $path;
    }

    /**
     * Get internal application directory path
     *
     * Example :
     *
     * windows:
     * $env->appPath('kernel') === C:/wamp/www/git/fortress/app/kernel/
     *
     * Linux:
     * $env->appPath('kernel') === /var/www/git/fortress/app/kernel/
     *
     * Todo : move to Sai/Environment/Path
     *
     * @param string $path
     * @return string
     * @throws \Exception
     */
    public function appPath( $path = 'base_path' )
    {
        $docRoot = $this->documentRoot();

        if( ! empty( $docRoot ) )
        {
            if( substr( $docRoot, -1 ) !== '/' ) {
                $docRoot .= '/';
            }

            switch( $path )
            {
                case('root'):
                case('doc_root'):
                    return dirname( dirname( $docRoot ) ); // todo - normalise path
                    break;

                default;
                case('base_path'):
                    return $docRoot;
                    break;

                case('app'):
                    return $docRoot . 'app/';
                    break;

                case('cache'):
                    return $docRoot . 'app/cache/';
                    break;

                case('config'):
                    return $docRoot . 'app/config/';
                    break;

                case('controllers'):
                    return $docRoot . 'app/controllers/';
                    break;

                case('kernel'):
                    return $docRoot . 'app/kernel/';
                    break;

                case('models'):
                    return $docRoot . 'app/models/';
                    break;

                case('modules'):
                    return $docRoot . 'app/modules/';
                    break;

                case('tmp'):
                    return $docRoot . 'tmp/';
                    break;

                case('vendor'):
                    return $docRoot . 'vendor/';
                    break;

                case('views'):
                    return $docRoot. 'views/';
                    break;
            }
        }

        throw new \Exception(
            'Environment :: Path not found' . escaped( $path )
        );
    }
}
