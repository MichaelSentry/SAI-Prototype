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
    private $file;

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
     * @return mixed
     */
    public function status(){
        return $this->status;
    }

    /**
     * Get cache file identifier
     *
     * @param Route $route
     * @param $path
     * @return string
     */
    public function getId( Route $route, $path )
    {
        $this->route  = $route;
        $this->uri    = $route->getUri();
        $this->id     = md5( $route->uri );
        $this->dir    = $path;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        $this->file  = $this->dir . $this->id . $this->ext;
        return $this->file;
    }


    /**
     * Fetch file from cache
     */
    public function read()
    {
        ob_start();
        require $this->file;
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
            @mkdir( $this->dir, 0775 );

            if( ! is_dir( $this->dir ) )
            {
                throw new \Exception(
                    'Cache Error :: Cache directory does not exist and could not be auto created'
                );
            }
        }

        $file = new \SplFileObject( $this->file, 'w+b');
        $file->fwrite( $content );
        $file = null;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function exists()
    {
        if( is_readable( $this->file ) )
        {
            if( ! in_array( $this->route->module, $this->exclusions ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear all files from cache directory
     *
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