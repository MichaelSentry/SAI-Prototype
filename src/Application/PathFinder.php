<?php
namespace NinjaSentry\Sai\Application;

use NinjaSentry\Sai\Config;

/**
 * SAI Prototype
 * -----------------------
 * ninjasentry.com 2016
 */

/**
 * Class PathFinder
 * @package NinjaSentry\Sai\Application
 */
class PathFinder
{
    /**
     * Validated domain
     * @var
     */
    private $domain;

    /**
     * @param Config $config
     * @param Config $paths
     * @param string $mode
     */
    public function __construct( Config $config, Config $paths, $mode = '' )
    {
        $this->config  = $config->get( $mode );
        $this->domain  = $this->config->server_name;
        $this->map     = $paths;
    }

    /**
     * Get application base HTTP path
     *
     * @param bool|false $ssl
     * @return string
     */
    public function http( $ssl = false )
    {
        $protocol = empty( $ssl )
            ? 'http'
            : 'https';

        $http_path  = $protocol . '://' . $this->domain;
        $http_path .= $this->appRoot();

        if( substr( $http_path, -1 ) !== '/' ) {
            $http_path = $http_path . '/';
        }

        return $http_path;
    }

    /**
     * Get internal application directory paths
     *
     * Example :
     *
     * windows:
     * $path->app('kernel') === C:/wamp/www/git/fortress/app/kernel/
     *
     * Linux:
     * $path->app('kernel') === /var/www/git/fortress/app/kernel/
     **
     * @param string $path
     * @return string
     * @throws \Exception
     */
    public function app( $path = 'base_path' )
    {
        $docRoot = $this->documentRoot();

        if( ! empty( $docRoot ) )
        {
            if( substr( $docRoot, -1 ) !== '/' ) {
                $docRoot .= '/';
            }

            if( $path === 'doc_root' ) {
                return $docRoot;
            }

            if( $this->map->has( $path ) ) {
                $value = $docRoot . $this->map->get( $path );
                return $value;
            }
        }

        throw new \Exception(
            'Environment :: Path not found' . escaped( $path )
        );
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
        if( empty( $this->config->document_root ) )
        {
            throw new \Exception(
                'Environment Error :: Expected document root setting was not found in env config'
            );
        }

        return $this->config->document_root;
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
}
