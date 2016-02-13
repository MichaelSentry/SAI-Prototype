<?php
namespace NinjaSentry\Sai;

/**
 * SAI Prototype
 * --------------------
 * Application Class
 *
 * ninjasentry.com 2016
 */

/**
 * "I never could have done what i have done without the habits of punctuality, order and diligence,
 * without that determination to concentrate myself on one subject at a time."
 * ~ Charles Dickens
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
     * Hosts Map
     * Accepted Host names
     *
     * @var array
     */
    public $hosts = [];

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
     * Get application environment mode
     * Production (default) | Development | Staging
     * @return string
     */
    public function getMode(){
        return $this->mode;
    }

    /**
     * Get application server name
     * @return mixed
     * @throws \Exception
     */
    public function serverName()
    {
        if( empty( $this->settings->server_name ) )
        {
            throw new \Exception(
                'Environment Error :: Expected server_name value is empty'
            );
        }

        return $this->settings->server_name;
    }

    /**
     * Load application config settings
     */
    private function settings()
    {
        /**
         * Load hosts map from config file
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
    }

    /**
     * Prepare Environment
     * Set Secure Foundation
     * Set TimeZone
     */
    private function prepare()
    {
        /**
         * Set error / exception / shutdown handlers
         */
        set_error_handler( 'sai_error_handler' );
        set_exception_handler( 'sai_exception_handler' );
        register_shutdown_function( 'sai_shutdown_handler' );

        /**
         * Set Application TimeZone
         */
        $this->setTimezone();

        /**
         * Set Application foundation settings based on Environment
         */
        $this->secureFoundation();
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
     *
     * Set Error Reporting based on environment mode
     * Prevent information disclosure by not displaying errors in production mode
     * Set Error Logging - Log all application errors to file
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
        ini_set( 'error_log', $this->settings->error_log ); // app_error.log

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
        if( isset( $this->settings->memory_limit ) ) {
            ini_set( 'memory_limit', $this->settings->memory_limit );
        }

        /**
         * Set maximum execution time [ Default : 30 - 60 seconds ]
         * http://php.net/manual/en/info.configuration.php#ini.max-execution-time
         */
        if( isset( $this->settings->max_exec_time ) ) {
            ini_set( 'max_execution_time', $this->settings->max_exec_time );
        }

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
     * Get host name from Client
     * assigned to var $this->domain
     *
     * @return bool|string
     * @throws \Exception
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

        if( empty( $this->hosts ) )
        {
            throw new \Exception(
                'Environment Error :: Expected hosts map is empty'
            );
        }

        if( ! is_array( $this->hosts ) )
        {
            $type = gettype( $this->hosts );

            throw new \Exception(
                'Environment Error :: Expected hosts map must be an array.'
                . 'Current data type ( ' . $type . ' )'
            );
        }

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

        if( empty( $validHost ) )
        {
            throw new \RuntimeException(
                'Environment Error :: No matching Server Name / Http Host was found'
            );
        }

        return $validHost;
    }

    /**
     * Auto Detect Server Environment ( Overrides the default Production Environment mode )
     * Host Match : Development server Host Name Match ( development | staging | production )
     * IP Match : A positive match for a white listed address sets new app env mode
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

        if( empty( $this->hosts ) ) {
            $validated = $this->matchIp();
        }
        elseif( is_array( $this->hosts ) ) {
            $validated = $this->matchHost();
        }

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
     * Detect environment based on preset server IP address ( IPv4 )
     *
     * Expects valid regex pattern
     * eg : '`^(10\.|127\.0\.0\.1|172\.(1[6-9|2[0-9]|3[01])|192\.168\.)`U'
     */
    private function matchIp()
    {
        $devIp = $this->settings->pattern_match;

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
}