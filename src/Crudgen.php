<?php

namespace crudgen;

use PDO;
use PDOException;
use ZipArchive;

class Crudgen implements adapter\GeneratorInterface
{
    //! target dir
    private $tar;
    //! template dir
    private $dir;
    //! template list
    private $tpl = array('controller','model','view_list','view_input','home');
    //! template extension
    private $ext = '.txt';
    //! php extension
    private $php = '.php';
    //! html extension
    private $html = '.html';
    //! ini extension
    private $ini = '.ini';
    //! project structure
    private $str = 'structure.zip';
    //! composer path
    private $cmp = 'composer';
    //! config files
    private $cff = array('app','database','menu','routes','system');
    //! token replacer
    private $token = array();
    const TOKEN = 'table|label|controller|model|primary_key|controller_namespace|model_namespace|date_field|option_list|namespace_list|route_prefix';
    //! config holder
    private $config = array();
    //! pdo object
    private $pdo;
    //! temp dir
    private $tempDir;
    //! stop the process
    private $stop = false;
    //! route content
    private $route = '';
    //! menu content
    private $menu = '';

    public function setTargetDir($dir)
    {
        $dir = H::fixslashes($dir);
        echo 'Setting project directory to: '.$dir.'...';
        $exists = file_exists($dir);
        $this->tar = $dir;
        echo $exists?'OK':'Fail';
        H::line();

        return $exists;
    }

    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);
        H::println('Checking config...'.
            (($ok = isset($config['namespace']['config'],
                $config['namespace']['controller'],
                $config['namespace']['model'],
                $config['namespace']['view']))?'OK':'Invalid configuration!'));

        !isset($config['structure']) ||$this->str = $config['structure'];

        return $ok;
    }

    public function checkTemplateDir()
    {
        H::println('Setting template directory to...'.
            (($ok = isset($this->config['generator']['template']) &&
                $this->config['generator']['template'] &&
                file_exists($this->config['generator']['template']))?
                    $this->config['generator']['template']:'nothing'));

        !$ok || $this->dir = H::fixslashes($this->config['generator']['template']);

        return $ok;
    }

    public function init()
    {
        H::println('Initializing...');
        $this->checkFile($this->dir.$this->str);
        foreach ($this->tpl as $tpl)
            if (!$this->checkFile($this->dir.$tpl.$this->ext))
                break;
        $this->checkComposer();
        $this->checkDB();
        H::println($this->stop?'failed':'OK');

        return !$this->stop;
    }

    public function run()
    {
        H::println('Starting make project...');

        $msg = 'done';
        if ($this->buildStructure()) {
            H::shift();
            echo 'Reading relations...';
            $db    = $this->config['database']['name'];
            $query = $this->pdo->query(<<<SQL
SELECT
`TABLE_NAME`,
`COLUMN_NAME`,
`REFERENCED_TABLE_NAME`,
`REFERENCED_COLUMN_NAME`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE `CONSTRAINT_SCHEMA` = '$db' AND
`REFERENCED_TABLE_SCHEMA` IS NOT NULL AND
`REFERENCED_TABLE_NAME` IS NOT NULL AND
`REFERENCED_COLUMN_NAME` IS NOT NULL
SQL
                );
            $referenced_table = array();
            $relations        = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                isset($relations[$row['TABLE_NAME']]) ||
                    $relations[$row['TABLE_NAME']] = array();
                $relations[$row['TABLE_NAME']][$row['COLUMN_NAME']] = array(
                    'table'=>$row['REFERENCED_TABLE_NAME'],
                    'field'=>$row['REFERENCED_COLUMN_NAME'],
                    );
                in_array($row['REFERENCED_TABLE_NAME'], $referenced_table) ||
                    array_push($referenced_table, $row['REFERENCED_TABLE_NAME']);
            }
            H::println('done');

            $query = $this->pdo->query('show tables');

            while ($table = $query->fetchColumn()) {
                H::shift();
                echo 'Constructing table: '.$table.'...';
                $table = new Table($table,
                    $this->pdo
                        ->query('show columns from '.$table)
                        ->fetchAll(PDO::FETCH_ASSOC),
                    isset($relations[$table])?$relations[$table]:array(),
                    in_array($table, $referenced_table));
                H::println('done');

                H::println('Creating crud of '.$table->model.'...', 1, 2);
                ob_start();
                $this->initToken($table);
                $this->createController($table);
                $this->createModel($table);
                $this->createViewInput($table);
                $this->createViewList($table);
                $this->appendRoute();
                $this->appendMenu();
                H::shiftAll(ob_get_clean(), 3);
                H::println('done', 1, 2);
            }

            $this->pdo = null;

            H::println('Finalizing...');
            ob_start();
            $this->finalize() || H::error('Finalization failed');
            H::shiftAll(ob_get_clean(), 2);
            H::println('done', 2);
        } else
            $msg = 'failed';

        H::println($msg);

        return true;
    }

    private function initToken(Table $table)
    {
        foreach (explode('|', self::TOKEN) as $value)
            $this->token($value, '');

        $this->token('controller_namespace', $this->config['namespace']['controller']);
        $this->token('model_namespace', $this->config['namespace']['model']);
        $this->token('controller', 'Crud'.$table->model);
        $this->token('model', $table->model);
        $this->token('table', $table->name);
        $this->token('label', $table->label);
        $this->token('primary_key', $table->primary_key);
        $this->token('date_field', $table->date_field);
        $this->token('option_list', $table->option_list);
        $this->token('namespace_list', $table->namespace_list);
        $this->token('route_prefix', 'crud_');
    }

    private function createController(Table $table)
    {
        $name = 'controller';
        echo ucfirst($name).' '.$this->token($name).'...';

        $this->write($this->tar.
            H::fixslashes($this->config['namespace'][$name]).
            $this->token($name).
            $this->php,
        file_get_contents($this->dir.$name.$this->ext));

        echo 'done';
        H::line();
    }

    private function createModel(Table $table)
    {
        $name = 'model';
        echo ucfirst($name).' '.$this->token($name).'...';

        $content = $table->schema();
        if ($t = $table->primaryKey())
            $content .= H::eol(2).$t;
        if ($t = $table->relation())
            $content .= H::eol(2).$t;
        if ($t = $table->optionList())
            $content .= H::eol(2).$t;
        if ($t = $table->data())
            $content .= H::eol(2).$t;

        $this->write($this->tar.
            H::fixslashes($this->config['namespace'][$name]).
            $this->token($name).
            $this->php,
        str_replace('{#content#}', trim($content),
            file_get_contents($this->dir.$name.$this->ext)));

        echo 'done';
        H::line();
    }

    private function createViewList(Table $table)
    {
        $name = 'view_list';
        echo ucfirst($name).'...';

        $this->token('column_header', $table->column_header);

        $dir = $this->tar.H::fixslashes($this->config['namespace']['view']).$table->name;
        is_dir($dir) || mkdir($dir, 0755, true);
        $this->write($dir.'/list'.$this->html,
            file_get_contents($this->dir.$name.$this->ext));

        echo 'done';
        H::line();
    }

    private function createViewInput(Table $table)
    {
        $name = 'view_input';
        echo ucfirst($name).'...';

        $this->token('fields_form', $table->fields_form);

        $dir = $this->tar.H::fixslashes($this->config['namespace']['view']).$table->name;
        is_dir($dir) || mkdir($dir, 0755, true);
        $this->write($dir.'/input'.$this->html,
            file_get_contents($this->dir.$name.$this->ext));

        echo 'done';
        H::line();
    }

    private function appendRoute()
    {
        $name = 'append route';
        echo ucfirst($name).'...';

        $this->route .= str_replace(
            array_keys($this->token),
            array_values($this->token),
            <<<ROUTE
GET @{#route_prefix#}{#table#}: /crud/{#table#} = {#controller_namespace#}\{#controller#}->index
GET /crud/{#table#}/data [ajax] = {#controller_namespace#}\{#controller#}->data
GET|POST /crud/{#table#}/input = {#controller_namespace#}\{#controller#}->input
GET /crud/{#table#}/delete [ajax] = {#controller_namespace#}\{#controller#}->delete
ROUTE
).H::eol(2);

        echo 'done';
        H::line();
    }

    private function appendMenu()
    {
        $name = 'append menu';
        echo ucfirst($name).'...';

        $this->menu .= str_replace(
            array_keys($this->token),
            array_values($this->token),
            <<<ROUTE
{#route_prefix#}{#table#} = {#label#}
ROUTE
).H::eol();

        echo 'done';
        H::line();
    }

    private function saveSystem()
    {
        echo 'Saving system...';

        $ui = H::fixslashes($this->config['namespace']['view']);
        $this->write($this->tar.
            H::fixslashes($this->config['namespace']['config']).
            'system'.$this->ini, <<<INI
; System Configuration
; Overrides moe variabels

[globals]
UI       = $ui
TEMPLATE = template/dashboard
INI
            );

        echo 'done';
        H::line();

        return true;
    }

    private function saveRoute()
    {
        echo 'Saving route...';

        $ns = $this->token('controller_namespace');
        $this->write($this->tar.
            H::fixslashes($this->config['namespace']['config']).
            'routes'.$this->ini, <<<INI
; routes

[routes]
GET @home: / = $ns\Home->index

{$this->route}
INI
            );

        echo 'done';
        H::line();

        return true;
    }

    private function saveMenu()
    {
        echo 'Saving menu...';

        $this->write($this->tar.
            H::fixslashes($this->config['namespace']['config']).
            'menu'.$this->ini, <<<INI
; menu

[menu]
{$this->menu}
INI
            );

        echo 'done';
        H::line();

        return true;
    }

    private function saveDatabase()
    {
        echo 'Saving Database...';

        $ts = '';
        foreach ($this->config['database'] as $key => $value)
            $ts .= $key.' = '.$value.H::eol();
        $ts = trim($ts);

        $this->write($this->tar.
            H::fixslashes($this->config['namespace']['config']).
            'database'.$this->ini, <<<INI
; Database Configuration

[DATABASE]
type = mysql
$ts
INI
            );

        echo 'done';
        H::line();

        return true;
    }

    private function saveApp()
    {
        echo 'Saving app...';

        $t = array(
            'name'=>'CRUD Generator',
            'desc'=>'CRUD Generator',
            'author'=>'eghojansu',
            'year'=>'2015',
            );
        !isset($this->config['app']) || $t += $this->config['app'];

        $ts = '';
        foreach ($t as $key => $value)
            $ts .= $key.' = '.(is_bool($value)?($value?'yes':'no'):$value).H::eol();
        $ts = trim($ts);

        $this->write($this->tar.
            H::fixslashes($this->config['namespace']['config']).
            'app'.$this->ini, <<<INI
; Application Configuration

[app]
$ts
INI
            );

        echo 'done';
        H::line();

        return true;
    }

    private function saveIndex()
    {
        echo 'saving index...';

        ksort($this->cff);
        $t = "'".implode("',".H::eol().H::tab(1,4)."'", $this->cff)."'";
        $this->token('config_files', $t);

        $index = $this->tar.'index'.$this->php;
        $this->write($index, file_get_contents($index), true);

        echo 'done';
        H::line();

        return true;
    }

    private function saveHome()
    {
        echo 'saving home...';

        $this->write($this->tar.
            H::fixslashes($this->config['namespace']['controller']).
            'Home'.
            $this->php,
        file_get_contents($this->dir.'home'.$this->ext));

        echo 'done';
        H::line();

        return true;
    }

    private function performComposer()
    {
        echo 'performing composer...';

        ob_start();
        $wd = getcwd();
        chdir($this->tar);
        exec($this->cmp.' install', $result);
        chdir($wd);
        ob_clean();

        echo 'done';
        H::line();

        return true;
    }

    private function finalize()
    {
        return ($this->saveSystem() &&
            $this->saveRoute() &&
            $this->saveMenu() &&
            $this->saveDatabase() &&
            $this->saveApp() &&
            $this->saveIndex() &&
            $this->saveHome() &&
            $this->performComposer());
    }

    private function write($file, $content, $overwrite = false)
    {
        return (file_exists($file) && !$overwrite)?-1:
            (int) file_put_contents($file, str_replace(
                array_keys($this->token), array_values($this->token),
                    str_replace(
                        array_keys($this->token), array_values($this->token), $content)));
    }

    private function buildStructure()
    {
        H::shift();
        echo 'Creating structure...';

        $zip = new ZipArchive;
        $msg = 'done';
        if ($zip->open($this->dir.$this->str) === true) {
            $zip->extractTo($this->tempDir);
            $zip->close();

            $stop  = !$this->checkNamespace($msg);
            $files = $stop?array():glob($this->tempDir.'*');
            foreach ($files as $value) {
                $real = $this->tar.str_replace($this->tempDir, '', $value);
                (file_exists($real) || !file_exists($value)) ||
                    rename($value, $real);
            }

            ($stop || !$stop = !file_exists($this->tar.'composer.json')) ||
                $msg = 'composer.json was not exists';
            ($stop || !$stop = !rename($this->tar.'dothtaccess', $this->tar.'.htaccess')) ||
                $msg = 'cannot rename .htaccess';
            ($stop || !$stop = !rename($this->tar.'dotgitignore', $this->tar.'.gitignore')) ||
                $msg = 'cannot rename .gitignore';
            ($stop || chmod($this->tar.'runtime', 0757)) ||
                $msg = 'cannot change mod runtime dir it need to be writable';
        } else
            $stop = true;

        H::println($msg);

        return !($this->stop = ($this->stop || $stop));
    }

    private function checkNamespace(&$msg)
    {
        foreach ($this->config['namespace'] as $value)
            if (!file_exists($this->tempDir.H::fixslashes($value))) {
                $msg = 'Invalid namespace '.$value;
                return false;
            }

        return true;
    }

    private function checkFile($file)
    {
        H::shift();
        printf('Checking file %s...%s',
            str_replace(array($this->tar, $this->dir), '', $file),
            ($stop = !is_file($file))?'file is not exists':'OK');
        H::line();

        return !($this->stop = ($this->stop || $stop));
    }

    private function checkComposer()
    {
        $composer = isset($this->config['composer'])?
            $this->config['composer']:$this->cmp;
        exec($composer.' -v', $tmp);
        $this->cmp = $composer;

        H::shift();
        printf('Checking composer...%s',
            ($stop = !isset($tmp[7]))?'Composer was not installed':'OK');
        H::line();

        return !($this->stop = ($this->stop || $stop));
    }

    private function checkDB()
    {
        $stop = !isset($this->config['database']['name'],
                $this->config['database']['username'],
                $this->config['database']['password']);

        H::shift();
        echo 'Checking database...';
        $msg = 'OK';
        if (!$stop)
            try {
                $dsn = 'mysql:dbname='.$this->config['database']['name'].';host=localhost';
                $this->pdo = new PDO($dsn, $this->config['database']['username'],
                    $this->config['database']['password']);
            } catch (PDOException $e) {
                $stop = true;
                $msg = $e->getMessage();
                $msg = 'Connection failed: '.ltrim(substr($msg, strrpos($msg, ']')+1));
            }
        H::println($msg);

        return !($this->stop = ($this->stop || $stop));
    }

    private function token($token, $value = null)
    {
        $token = '{#'.$token.'#}';
        if (isset($value) || !isset($this->token[$token]))
            $this->token[$token] = $value;

        return $this->token[$token];
    }

    public function __construct()
    {
        $this->tempDir = H::fixslashes(sys_get_temp_dir()).'crudgen/';
    }
}
