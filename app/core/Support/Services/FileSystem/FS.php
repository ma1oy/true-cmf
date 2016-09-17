<?php

namespace Truth\Support\Services\FileSystem;

use Closure;
use InvalidArgumentException;
use UnexpectedValueException;
use Truth\Support\Services\Repository\FileRepository;
use Truth\Support\Services\FileSystem\Exceptions\FileNotFoundException;
use Truth\Support\Services\FileSystem\Exceptions\UnreadableFileException;

class FS
{
    const TAKE         = 'take';
    const READ         = 'read';
    const ASSOC        = 'getAssoc';
    const INSERT       = 'insert';
    const INVOLVE      = 'involve';
    const INSERT_ONCE  = 'insertOnce';
    const INVOLVE_ONCE = 'involveOnce';

    protected $basedir;

    /**
     * FS constructor with base directory path.
     *
     * @param string $base
     */
    public function __construct($base = '')
    {
        $this->basedir = $base . '/';
    }

    public function isFile($filePath) {
        return is_file($this->basedir . $filePath);
    }

    public function isDir($filePath) {
        return is_dir($this->basedir . $filePath);
    }

    public function exists($filePath) {
        return file_exists($this->basedir . $filePath);
    }

    /**
     * If file exists and readable calls callback function else throw exception.
     *
     * @param string $filePath
     * @param Closure $callback
     *
     * @throws FileNotFoundException
     */
    private function isFileCallback($filePath, $callback) {
        if ($this->isFile($filePath)) {
            return $callback($filePath);
        } else {
            throw new FileNotFoundException('File "' . $filePath . '" you try to open is not found'); // TODO: Envisage
        }
    }

    /**
     * If file exists and readable include it else throw exception.
     *
     * @param string $filePath
     * @return mixed
     */
    public function insert($filePath) {
        return self::isFileCallback($filePath, function($filePath) {
            return include $this->basedir . $filePath;
        });
    }

    /**
     * If file exists and readable include it once else throw exception.
     *
     * @param string $filePath
     * @return mixed
     */
    public function insertOnce($filePath) {
        return self::isFileCallback($filePath, function($filePath) {
            return include_once $this->basedir . $filePath;
        });
    }

    /**
     * If file exists and readable require it else throw exception.
     *
     * @param string $filePath
     * @return mixed
     */
    public function involve($filePath) {
        return self::isFileCallback($filePath, function($filePath) {
            return require $this->basedir . $filePath;
        });
    }

    /**
     * If file exists and readable require it once else throw exception.
     *
     * @param string $filePath
     * @return mixed
     */
    public function involveOnce($filePath) {
        return self::isFileCallback($filePath, function($filePath) {
            return require_once $this->basedir . $filePath;
        });
    }

    /**
     * If file exists and readable get (include / require / include_once / require_once)
     * it (once / more) else throw exception.
     *
     * @param string $filePath
     * @param string $getMethod
     * @return mixed
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function apply(/*string*/ $filePath, /*string*/ $getMethod) {
        return self::isFileCallback($filePath, function($filePath) use($getMethod) {
            if (is_string($getMethod)) {
                if (is_callable([get_called_class(), $getMethod])) {
                    return $this->$getMethod($filePath);
                } else {
                    throw new UnexpectedValueException('exceptions.unexpected_value'); // TODO: Envisage
                }
            } else {
                throw new InvalidArgumentException('exceptions.invalid_argument'); // TODO: Envisage
            }
        });
    }

    /**
     * If file exists and readable get content as string without parsing else throw exception.
     *
     * @param string $filePath
     * @return mixed
     */
    public function take($filePath) {
        return self::isFileCallback($filePath, function ($filePath) {
            return file_get_contents($this->basedir . $filePath);
        });
    }

    /**
     * If file exists and readable execute content without parsing else throw exception.
     *
     * @param string $filePath
     * @return mixed
     */
    public function read($filePath) {
        return self::isFileCallback($filePath, function ($filePath) {
            return readfile($this->basedir . $filePath);
        });
    }

    /**
     * @param string $filePath
     * @return FileRepository
     */
    public function getAssoc($filePath) {
        return new FileRepository($this, $this->basedir . $filePath);
    }

    /**
     * Get basedir for all paths
     *
     * @return string
     */
    public function getBasedir() {
        return $this->basedir;
    }

    public function clear($filePath, $size = 0) {
        $handle = fopen($filePath, 'r+');
        ftruncate($handle, $size);
        fclose($handle);
    }

    public function parse($filePath, $type = File::PHP) {
        return new File($filePath);
    }
}
