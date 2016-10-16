<?php
namespace T\Services\Multilingual;

use T\Services\FileSystem\FS;
use T\Services\ArrayObject\MultiFileArrayObject;
use T\Interfaces\Lang as LangInterface;

class Lang extends MultiFileArrayObject implements LangInterface
{
    const WRAP_TAG  = 'strong';
    const BASE_LANG = 'en-EN';
    
    /**
     * @param FS     $fileSystem
     * @param string $filePath
     * @param string $separator
     */
    public function __construct(FS &$fileSystem, $filePath = null, $separator = '.') {
        parent::__construct($fileSystem, $filePath);
    }
    
    public function load($lang) {
        $this->loadFiles($lang);
    }
    
    public function parse($str, array $data, $tag = null) {
        return preg_replace_callback('|{{\s*\w+\s*}}|U', function ($matches) use ($data, $tag) {
            $key = trim($matches[0], '{ }');
            return isset($data[$key]) ? $this->tag($data[$key], $tag) : '';
        }, $str);
    }
    
    public function exception($exception, array $data) {
        return $this->debug('exceptions', $exception, $data);
    }
    
    public function notice($notice, array $data) {
        return $this->debug('notices', $notice, $data);
    }
    
    protected function tag($data, $tag) {
        return $tag ? "<$tag>$data</$tag>" : '';
    }
    
    protected function debug($placeholder, $text, array $data) {
        $debug = &$this->data['debug'];
        return $this->parse("{$debug['before']}{$debug[$placeholder][$text]}{$debug['after']}", $data, self::WRAP_TAG);
    }
}
