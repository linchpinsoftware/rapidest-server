<?php

namespace Rapidest;

class Scanner
{
    const CONTENT = 'content';
    const DIRECTORIES_ONLY = 'directories_only';
    const INCLUDE_DIRECTORIES = 'include_directories';
    const PATTERN = 'pattern';
    const RECURSIVE = 'recursive';

    /**
     * Default options
     *
     * @var array
     */
    public $options = [
        self::CONTENT             => null,
        self::DIRECTORIES_ONLY    => false,
        self::INCLUDE_DIRECTORIES => false,
        self::PATTERN             => null,
        self::RECURSIVE           => false,
    ];

    /**
     * Scanned directories
     *
     * @var array
     */
    public $scanned = [];

    /**
     * Scan a directory for files
     *
     * @param  string $dir
     * @param  array  $options
     * @return array
     * @throws Exception
     */
    public function scan($dir, $options = [])
    {        
        if (in_array($dir, $this->_scanned)) {
            return [];
        }

        $dir = rtrim($dir, DS);
        $files = [];
        $filesRecursive = [];
        $this->_scanned[] = $dir;
        $options = array_merge($this->_defaultOptions, $options);
        $pattern = $options[self::PATTERN];
        $content = $options[self::CONTENT];

        if (is_dir($dir) || is_dir($dir = '/chroot' . $dir)) {
            if ($dh = opendir($dir)) {
                while (false !== ($file = readdir($dh))) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    if (is_dir($path = $dir . DS . $file)) {
                        if ($options[self::INCLUDE_DIRECTORIES]
                            || $options[self::DIRECTORIES_ONLY]
                        ) {
                            $files[] = $path;
                        }
                        if ($options['recursive'] === true) {
                            $filesRecursive = array_merge(
                                $filesRecursive,
                                $this->scan($path, $options)
                            );
                        }
                    } elseif (!is_readable($path)) {
                        throw new Exception('File is not readable: ' . $file);
                    } elseif ((is_null($pattern) || preg_match($pattern, $file))
                        && (is_null($content) || preg_match($content, file_get_contents($path)))
                        && !$options[self::DIRECTORIES_ONLY]
                    ) {
                        $files[] = $path;
                    }
                }
                closedir($dh);
            } else {
                throw new Exception('Failed to open directory ' . $dir);
            }
        } else {
            throw new Exception('Not a directory: ' . $dir);
        }

        return array_merge($files, $filesRecursive);
    }
}