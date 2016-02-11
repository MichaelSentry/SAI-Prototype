<?php
namespace NinjaSentry\Sai\Application;

/**
 * SAI Prototype
 * -----------------------
 * ninjasentry.com 2016
 */

use NinjaSentry\Sai\Config;

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
     * @param string $mode
     */
    public function __construct( Config $config, $mode = '' ){
        $this->config = $config->get( $mode );
        $this->domain = $this->config->server_name;
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
     * $env->appPath('kernel') === C:/wamp/www/git/fortress/app/kernel/
     *
     * Linux:
     * $env->appPath('kernel') === /var/www/git/fortress/app/kernel/
     *
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

                case('assets'):
                    return $docRoot . 'public/assets/';
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
                    return $docRoot . 'app/';
                    break;

                case('public'):
                    return $docRoot . 'public/';
                    break;

                case('redirects'):
                    return $docRoot . 'app/config/redirects/';
                    break;

                case('tmp'):
                    return $docRoot . 'tmp/';
                    break;

                case('themes'):
                    return $docRoot . 'public/themes/';
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

    /**
     * Get application root path set in app/config/env.php
     * Used for building internal application path
     *
     * @return string
     */
    private function documentRoot(){
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
