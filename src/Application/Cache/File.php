<?php
namespace NinjaSentry\Sai\Application\Cache;

use NinjaSentry\Sai\Route;
use NinjaSentry\Sai\Config;

/**
 * Class File
 * @package NinjaSentry\Sai\Application\Cache
 */
class File implements Cached
{
    /**
     * Cache directory permissions
     */
    const NEW_DIR_PERMISSION = 0755;

    /**
     * @var
     */
    private $dir;

    /**
     * @var
     */
    private $id;

    /**
     * @var
     */
    private $route;

    /**
     * @var
     */
    private $uri;

    /**
     * @param Config $config
     */
    public function __construct( Config $config )
    {
        $this->config     = $config;
        $this->status     = $config->get('cache.status');
        $this->exclusions = $config->get('cache.exclusions');
        $this->ext        = $config->get('cache.file_ext');
    }

    /**
     * Get cache file identifier
     * @param Route $route
     * @param $path
     * @return string
     */
    public function getId( Route $route, $path )
    {
        $this->route = $route;
        $this->dir   = $path;
        $this->uri   = $route->getUri();
        $this->id    = md5( $this->uri );
    }

    /**
     * @return string
     */
    public function getFile(){
        return $this->dir . $this->id . $this->ext;
    }

    /**
     * Get cache enabled status ( true | False )
     * @return mixed
     */
    public function status(){
        return $this->status;
    }

    /**
     * Cache action status
     * @return bool
     */
    public function able()
    {
        if( $this->status() )
        {
            if( $this->exists() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if cache file exists and not in exclusions list
     *
     * @return bool
     * @throws \Exception
     */
    public function exists()
    {
        if( ! in_array( $this->route->module, $this->exclusions ) )
        {
            if( is_readable( $this->getFile() ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch file from cache
     */
    public function read()
    {
        ob_start();
        require $this->getFile();
        return ob_get_clean();
    }

    /**
     * Save generated page source to cache file
     *
     * @param $content
     * @throws \Exception
     */
    public function save( $content )
    {
        if( empty( $this->dir ) || ! is_string( $this->dir ) )
        {
            throw new \Exception(
                'Cache Error :: Expected cache directory path not found'
            );
        }

        if( ! is_dir( $this->dir ) )
        {
            @mkdir( $this->dir, self::NEW_DIR_PERMISSION );

            if( ! is_dir( $this->dir ) )
            {
                throw new \Exception(
                    'Cache Error :: Cache directory does not exist and could not be auto created'
                );
            }
        }

        $file = new \SplFileObject( $this->getFile(), 'w+b');
        $file->fwrite( $content );
        $file = null;
    }

    /**
     * Clear all files from cache directory
     * @throws \Exception
     */
    public function purge()
    {
        if( empty( $this->dir ) )
        {
            throw new \Exception(
                'Page Cache Error :: Cache directory path must be set in app config'
            );
        }

        $di = new \DirectoryIterator( $this->dir );

        foreach( $di as $file )
        {
            if( $file->isDot() ) {
                continue;
            }

            if( $file->isFile() )
            {
                $filePath = str_replace( '\\', '/', $file->getPathname() );
                unlink( $filePath );
            }
        }
    }
}