<?php

namespace crudgen;

use ZipArchive;

class Crudgen
{
    //! target dir
    private $tar;
    //! template dir
    private $dir;
    //! composer path
    private $cmp;
    //! composer command
    private $cmpCmd = 'install';
    //! project structure
    private $str = 'structure.zip';
    //! temp dir
    private $tempDir;
    //! path lookup
    private $path = array();
    //! for finalizing
    private $final = array();
    //! database
    private $db;
    //! token generator
    private $token = '\\moegen\\Moegen';

    public function run()
    {
        C::start('Starting make project...', 0, 1);

        C::start('Building structure...', 1);
        $this->buildStructure($msg) || C::error($msg);
        C::finish();

        $tokenGen = $this->token;
        foreach ($this->db->table as $key => $table) {
            C::start('Creating crud of '.$table->model.'...', 2);

            $token = new $tokenGen($table);
            $this->createCrud($token);

            C::finish();
        }

        C::start('Finalizing...', 1);
        isset($token) || C::error('unable to finalizing');
        $this->finalize($token);
        C::finish();

        $this->performComposer();

        C::finish();
    }

    private function createCrud(adapter\AbstractToken $token)
    {
        foreach (C::config('files')?:array() as $key => $value) {
            $path = isset($value['path'])?C::fixslashes($value['path']):'';

            $content = '';
            $file    = $token->replaceToken($value['name']);
            $e       = C::ext($file);
            ($path || !isset($this->path[$e])) || $path = $this->path[$e];

            if (isset($value['template'])) {
                $e = C::ext($value['template']);
                ($path || !isset($this->path[$e])) || $path = $this->path[$e];

                $content = file_get_contents($this->dir.$value['template']);
            }

            !$path || $path = $token->replaceToken($path);

            if (isset($value['method'])) {
                $args = array($content);
                $content = method_exists($token, $value['method'])?
                    call_user_func_array(array($token, $value['method']), $args):
                    C::call($value['option']['method'], $args);
            }
            elseif (isset($value['replaceOriginal']))
                $content = file_get_contents($this->tar.$path.$value['name']);
            elseif (isset($value['copyConfig'])) {
                $content = '';
                foreach (C::config($key) as $key2 => $value2)
                    $content .= $key2.' = '.$value2.C::eol();
                $content = trim($content);
            }

            $path = $this->tar.$path.$file;
            $content = $token->replaceToken($content);
            if (isset($value['createAtTheEnd'])) {
                $sectionName = $key;
                !isset($value['sectionName']) || $sectionName = $value['sectionName'];
                !isset($value['sectionUppercase']) || $sectionName = strtoupper($key);
                $option = array(
                    'file'=>$path,
                    'sectionName'=>$sectionName,
                    'method'=>$value['createAtTheEnd'],
                    'replaceOriginal'=>isset($value['replaceOriginal']),
                    );

                if (isset($value['template']) && !isset($value['noConcat']))
                    if (isset($this->final[$key]))
                        $this->final[$key]['content'] .= C::eol(2).$content;
                    else
                        $this->final[$key] = array('content'=>$content)+$option;
                else
                    $this->final[$key] = array('content'=>$content)+$option;
            } else
                $this->write($path, $token->replaceToken($content)) || C::error('Unable to write '.$path);
        }
    }

    private function finalize(adapter\AbstractToken $token)
    {
        foreach ($this->final as $key => $value) {
            if (!is_bool($value['method'])) {
                $args = array($value['content']);
                $content = method_exists($token, $value['method'])?
                    call_user_func_array(array($token, $value['method']), $args):
                    C::call($value['method'], $args);
                !$content || $value['content'] = $content;
                unset($content);
            }

            if (C::ext($value['file'])==='ini')
                $value['content'] = '['.$value['sectionName'].']'.C::eol().$value['content'];

            $this->write($value['file'], $token->replaceToken($value['content']), $value['replaceOriginal']) ||
                C::error('Unable to write '.$value['file']);
        }
    }

    private function buildStructure(&$msg)
    {
        $zip = new ZipArchive;
        if ($zip->open($this->str) === true) {
            $zip->extractTo($this->tempDir);
            $zip->close();

            $files = glob($this->tempDir.'*');
            foreach ($files as $value) {
                $real = $this->tar.str_replace($this->tempDir, '', $value);
                (file_exists($real) || !file_exists($value)) ||
                    rename($value, $real);
            }

            (!$stop = ($this->cmp && !file_exists($this->tar.'composer.json'))) ||
                $msg = 'composer.json was not exists';
            ($stop || !$stop = (file_exists($this->tar.'dothtaccess') &&
                !rename($this->tar.'dothtaccess', $this->tar.'.htaccess'))) ||
                $msg = 'cannot rename .htaccess';
            ($stop || !$stop = (file_exists($this->tar.'dotgitignore') &&
                !rename($this->tar.'dotgitignore', $this->tar.'.gitignore'))) ||
                $msg = 'cannot rename .gitignore';
            foreach ($stop?array():(C::config('chmod')?:array()) as $dir=>$perm) {
                if (!file_exists($this->tar.$dir) ||
                    !chmod($this->tar.$dir, C::perm($perm))) {
                    $msg = 'cannot chmod '.$dir.' to '.$perm;
                    break;
                }
            }
        } else {
            $msg = 'cannot open '.$this->str;
            $stop = true;
        }

        return !$stop;
    }

    private function write($file, $content, $overwrite = false)
    {
        if (file_exists($file) && !$overwrite)
            return -1;
        $dir = dirname($file);
        is_dir($dir) || mkdir($dir, 0755, true);
        return (int) file_put_contents($file, $content);
    }

    private function performComposer()
    {
        if (!$this->cmp)
            return;

        C::start('Performing composer...');

        $wd = getcwd();
        chdir($this->tar);
        exec($this->cmp.' '.$this->cmpCmd, $result);
        chdir($wd);

        C::finish();
    }

    public function __construct($dir)
    {
        C::start('Setting project directory to: '.$dir.'...');
        if (file_exists(realpath($dir)))
            $dir = realpath($dir);
        else
            !mkdir($dir, 0755) || $dir = realpath($dir);
        $this->tar = C::fixslashes($dir);
        is_dir($this->tar) || C::error('invalid dir');
        C::finish();

        $c = C::config();
        C::start('Checking config...');
        isset($c['generator']['template'],
              $c['database']['name'],
              $c['database']['host'],
              $c['database']['username'],
              $c['database']['password'],
              $c['fixed'],
              $c['files']) || C::error('invalid config');
        C::finish();

        C::start('Setting template directory to: '.
            C::config('generator')['template'].'...');
        file_exists(C::config('generator')['template']) || C::error('fail');
        $this->dir = C::fixslashes(C::config('generator')['template']);
        C::finish();

        C::start('Checking lookup path...');
        $paths = C::config('path')?:array();
        foreach ($paths as $key => $value)
            $paths[$key] = C::fixslashes($value);
        $this->path = $paths;
        C::config('path', $paths);
        C::finish();

        C::start('Checking files...', 0, 1);
        ob_start();
        $ok    = true;
        $nss   = array();
        foreach (C::config('files') as $key => $value) {
            C::start('Checking: '.$key.'...');
            isset($value['name']) || C::error('no key name');
            $msg  = 'OK';
            $path = isset($value['path'])?C::fixslashes($value['path']):'';
            $e    = C::ext($value['name']);
            ($path || !isset($paths[$e])) || $path = $paths[$e];
            if (isset($value['template'])) {
                $ok = file_exists($this->dir.$value['template']);
                $ok || $msg = 'file not exists';
            }
            elseif (isset($value['copyConfig'])) {
                $ok = isset($c[$key]);
                $ok || $msg = 'no '.$key.' in config';
            }

            !$path || $nss[$key.'_namespace'] = rtrim(strtr($path, '/', '\\'), '\\');

            C::finish($msg);

            if ($ok) continue;
            else break;
        }
        C::config('namespaces', $nss);
        C::shiftAll(ob_get_clean(), 1);
        $ok || C::error('Failed');
        C::finish();

        if (isset($c['composer'], $c['composer']['path'])) {
            C::start('Checking composer...');
            exec($c['composer']['path'].' -v', $tmp);
            isset($tmp[7]) || C::error('Composer was not installed');
            $this->cmp = $c['composer']['path'];
            !isset($c['composer']['command']) || $this->cmpCmd = $c['composer']['command'];
            C::finish();
        }

        if (isset($c['generator']['token']))
            $this->token = $c['generator']['token'];

        $this->str = $this->dir.$this->str;
        if (isset($c['generator']['structure'])) {
            C::start('Checking structure...');
            if (file_exists($c['generator']['structure']))
                $this->str = $c['generator']['structure'];
            elseif (file_exists($this->dir.$c['generator']['structure']))
                $this->str = $this->dir.$c['generator']['structure'];
            else
                C::error('Structure doesn\'t exists');
            C::finish();
        }

        C::start('Construct hole database...', 0, 1);
        ob_start();
        $this->db = new Database($c['database']);
        C::shiftAll(ob_get_clean(), 1);
        C::finish();

        $this->tempDir = C::fixslashes(sys_get_temp_dir()).'crudgen/';
    }
}
