<?php

namespace app\controller;

use moe\Base;
use moe\DB;
use moe\Instance;
use PDO;

class Crudgen
{
    protected $config = array(
        'database' => array(
            'type'     => 'mysql',
            'name'     => '',
            'username' => 'root',
            'password' => '',
            'server'   => 'localhost',
            'charset'  => 'utf8',
            'port'     => 3306,
            ),
        'path' => array(
            'controller' => '',
            'model'      => '',
            'view'       => '',
            'config'     => '',
            ),
        'template' => array(
            'controller' => '',
            'model'      => '',
            'view_list'  => '',
            'view_input' => '',
            ),
        'route_prefix' => '',
        'cnamespace'   => '',
        'mnamespace'   => '',
        );

    const TOKEN      = 'table|relation|controller|model|columns_header|columns_select|fields_form|schema|primary_keys|primary_key|cnamespace|mnamespace';
    protected $token = array();
    protected $config_path;

    public function init($moe)
    {
        $moe->set('POST', $this->config);
        $moe->send('app/init');
    }

    public function preview($moe)
    {
        if (! ($moe->get('POST.database.name')
            && $moe->get('POST.database.username')
            && $moe->get('POST.path.controller')
            && $moe->get('POST.path.model')
            && $moe->get('POST.path.view')
            && $moe->get('POST.route_prefix')
            && is_dir($moe->get('POST.path.controller'))
            && is_writable($moe->get('POST.path.controller'))
            && is_dir($moe->get('POST.path.model'))
            && is_writable($moe->get('POST.path.model'))
            && is_dir($moe->get('POST.path.view'))
            && is_writable($moe->get('POST.path.view'))
            && is_dir($moe->get('POST.path.config'))
            && is_writable($moe->get('POST.path.config'))
        )) {
            $moe->pre($moe->get('POST.path'));
            $moe->set('message', 'Invalid Configuration');
            $moe->send('app/init');
            return;
        }

        $this->config['template']     = array_merge($this->config['template'],
            $moe->get('POST.template'));
        $this->config['database']     = array_merge($this->config['database'],
            $moe->get('POST.database'));
        $this->config['path']         = array_merge($this->config['path'],
            $moe->get('POST.path'));
        foreach ($this->config['path'] as $key => $value)
            $this->config['path'][$key] = rtrim($moe->fixslashes($value), '/').'/';
        $this->config['route_prefix'] = rtrim($moe->fixslashes(
            $moe->get('POST.route_prefix')), '/').'/';
        $this->config['cnamespace']   = rtrim($moe->get('POST.cnamespace'), '\\');
        $this->config['mnamespace']   = rtrim($moe->get('POST.mnamespace'), '\\');

        $db    = DB::instance($this->config['database'])->pdo;
        $query = $db->query('show tables');
        $eol   = "\n";
        $php   = '.php';
        $html  = '.html';
        $s3    = $this->space(4*3);
        $i     = -1;
        $crud  = $temp = $routes = array();

        foreach ($this->config['template'] as $key => $value)
            $temp[$key] = str_replace('{#content#}',
                ltrim($moe->read($this->config_path.'template.'.$key.'.ini')),
                $value);
        $temp['route']  = str_replace(array(
            '{#cnamespace#}',
            '{#route_prefix#}'), array(
            $this->config['cnamespace'],
            $this->config['route_prefix'],
            ), $moe->read($this->config_path.'template.routes.ini'));

        $this->token('cnamespace', $this->config['cnamespace']);
        $this->token('mnamespace', $this->config['mnamespace']);
        while ($table = $query->fetchColumn()) {
            $model          = $this->model($table);
            $controller     = 'Crud'.$model;
            $queryC         = $db->query('show columns from '.$table);
            $columns_header = $columns_select = $fields_form = '';
            $schema = $pk   = array();
            $relation       = $this->relation($table, $db);
            while ($def = $queryC->fetch(PDO::FETCH_OBJ)) {
                $field           = ucwords(str_replace('_',' ', $def->Field));
                $columns_header .= $s3.'<th>{{ @fields.'.$def->Field.' }}</th>'.$eol;
                $columns_select .= $def->Field.','.$eol;
                $fields_form    .= $this->form((array) $def, $field).$eol;
                $schema[]        = $this->schema($def, $field, $relation);
                $def->Key != 'PRI'   || $pk[] = $def->Field;
            }

            $this->token('controller',     $controller);
            $this->token('model',          $model);
            $this->token('table',          $table);
            $this->token('columns_header', trim($columns_header));
            $this->token('columns_select', rtrim($columns_select, ','.$eol));
            $this->token('fields_form',    rtrim($fields_form));
            $this->token('schema',         "array(".$eol.$s3.
                implode(",".$eol.$s3, $schema).$eol.$s3.")");
            $this->token('primary_keys',   $moe->stringify($pk));
            $this->token('primary_key',    array_shift($pk));
            $this->token('relation',       $this->relationString($relation));

            ++$i;
            foreach ($temp as $key => $value)
                $crud[$i][$key] = array(
                    'file'=>((isset($this->config['path'][$key])?
                        $this->config['path'][$key]:'').
                        ($key=='controller'?$controller.$php:
                            ($key=='model'?$model.$php:
                                (strpos($key, 'view')==0?$this->config['path']['view'].$table.'/'.
                                    explode('_', $key)[1].$html:'xx')))),
                    'content'=>str_replace(
                        array_keys($this->token),
                        array_values($this->token),
                        $value));
            $routes[] = array_pop($crud[$i])['content'];
        }
        $moe->set('POST.cruds', $crud);
        $moe->set('POST.route', array(
            'file'=>$this->config['path']['config'].'crud.route.ini',
            'content'=>'[routes]'.$eol.implode($eol, $routes)));
        $moe->send('app/preview');
    }

    public function finish($moe)
    {
        $success = array();
        $skipped = array();
        $failed  = array();
        $files   = $moe->get('POST.cruds');
        $files[] = array('xx'=>$moe->get('POST.route'));
        foreach ($files as $i => $crud)
            foreach ($crud as $type => $def)
                if (file_exists($file = $moe->fixslashes($def['file'])) &&
                    !isset($def['overwrite']) && !$def['overwrite']
                )
                    array_push($skipped, $file);
                else {
                    $dir = substr($file, 0, strrpos($file, '/'));
                    if (!file_exists($dir)) {
                        mkdir($dir,0757,true);
                        chmod($dir, 0757);
                    }
                    if ($moe->write($file, $def['content'])) {
                        array_push($success, $file);
                        chmod($file, 0757);
                    }
                    else
                        array_push($failed, $file);
                }
        $moe->set('success', $success);
        $moe->set('skipped', $skipped);
        $moe->set('failed', $failed);
        $moe->send('app/finish');
    }

    public function howto($moe)
    {
        $this->pageTitle('How To');
        $moe->send('app/howto');
    }

    private function model($table)
    {
        return ucfirst(Instance::camelcase($table));
    }

    private function relation($table, $db)
    {
        $query = $db->query('show create table '.$table);
        $structure = $query->fetch(PDO::FETCH_NUM)[1];
        unset($db, $query);
        $relation = array();
        if (preg_match_all('/FOREIGN KEY \(\W*(?<fil1>[a-zA-Z_]+)\W*\) REFERENCES \W*(?<tab>[a-zA-Z_]+)\W* \(\W*(?<fil2>[a-zA-Z_]+)\W*\)/',
            $structure, $match, PREG_SET_ORDER))
            foreach ($match as $tab)
                $relation[$tab['fil1']] = array($tab['tab'],$tab['fil2']);

        return $relation;
    }

    private function def(array $col)
    {
        $field = $col['Type'];
        if (preg_match('/^(?<col>[a-z_]+)/i', $field, $match))
            $field = $match['col'];
        switch ($field) {
            case 'date':
                $default = 'date(\'Y-m-d\')';
                break;
            case 'year':
                $default = 'date(\'Y\')';
                break;
            case 'time':
                $default = 'date(\'H:i:s\')';
                break;
            case 'timestamp':
            case 'datetime':
                $default = 'date(\'Y-m-d H:i:s\')';
                break;
            default:
                $default = $col['Default']?:($this->isNumber($col['Field'])?0:null);
                break;
        }
        return $default;
    }

    private function isNumber($field)
    {
        return preg_match('/^(bit|tinyint|smallint|mediumint|int|integer|bigint|real|double|float|decimal|numeric)/i', $field);
    }

    private function isDate($field)
    {
        return preg_match('/^(date|time|timestamp|datetime)/i', $field);
    }

    private function isLongtext($field)
    {
        return preg_match('/^(tinyblob|blob|mediumblob|longblob|tinytext|text|mediumtext|longtext)/i', $field);
    }

    private function relationString($rel)
    {
        $relation = '';
        $eol      = "\n";
        $s3       = $this->space(4*3);
        foreach ($rel as $fil1=>$tab)
            $relation .= str_replace(
                array('{tab}', '{fil1}', '{fil2}', '{EOL}', '{S3}'),
                array($tab[0], $fil1, $tab[1], $eol, $s3),
                "'{tab}'=>'join {join} on {join}.{fil2} = {table}.{fil1}',{EOL}{S3}");
        return 'array('.($relation?$eol.$s3.$relation:'').')';
    }

    private function schema($col, $field, $relation)
    {
        $col = (array) $col;
        $def = $this->def($col);
        return str_replace(
            array('EOL', 'S4'),
            array("\n", $this->space(4*4)),
            "'$col[Field]'=>array(EOLS4'$field',EOLS4".
                $this->filter($col,$relation).",".($def?'EOLS4'.$def:'')."EOLS4)");
    }

    private function filter(array $col, $relation)
    {
        $filter = "'trim'";
        $col['Null']=='YES' || $filter .= ",'required'";
        $length = 0;
        if (preg_match('/\((?<len>.*)\)/', $col['Type'], $match))
            $length = is_numeric($match['len'])? $match['len']:
                explode(',', str_replace(array('"', "'"), '', $match['len']));
        if (is_array($length))
            $filter .= ",'in_array'=>".Instance::stringify($length);
        elseif ($length)
            if ($length == 1)
                $filter .= ",'in_array'=>array(0,1)";
            else
                $filter .= ",'max_length'=>$length";
        if ($col['Key']==='UNI' || ($col['Key']=='PRI' &&
            strpos($col['extra'], 'auto_increment')===false))
            $filter .= ",'unique".$this->model($col['Field'])."'";
        if (isset($relation[$col['Field']]))
            $filter .= ",'exists'=>'".addslashes($this->config['mnamespace'].'\\').
                $this->model($relation[$col['Field']][0])."->exists'";

        return "array($filter)";
    }

    private function form(array $col, $field)
    {
        $isRadio    = false;
        $isNumber   = $this->isNumber($col['Type']);
        $isDate     = $this->isDate($col['Type']);
        $isLongtext = $this->isLongtext($col['Type']);
        $opt        = array();
        if (preg_match('/\((?<num>\d+)\)/', $col['Type'], $match)) {
            if ($match['num']==1) {
                $isRadio = true;
                $opt = array(1=>'Yes',0=>'No');
            }
        } elseif (preg_match('/\((?<opt>.*)\)/', $col['Type'], $match)) {
            $opt = explode(',', str_replace(array('"', "'"), '', $match['opt']));
            $isRadio = count($opt)<4;
        }

        $space = 4;
        $form  = '';
        $eol   = "\n";
        if ($isRadio)
            foreach ($opt as $key => $value)
                $form .= str_replace(array(
                    'VALUE',
                    'LABEL',
                    ), array(
                    ($isNumber?$key:$value),
                    $value,
                    ),
                    'S3<label class="radio-inline">EOL'.
                    'S4<input type="radio" name="FIELD" value="VALUE"'.
                    '{{ @POST.FIELD==VALUE?" checked":"" }}REQUIRED> LABELEOL'.
                    'S3</label>EOL');
        elseif ($isDate)
            $form = 'S3{~ @x = explode(\'-\', @POST.FIELD) ~}EOL'.
                'S3<select style="width: 70px; display: inline" name="FIELD[d]" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Helper::optionRange(1, 31, @x[2]) }}EOL'.
                'S3</select>EOL'.
                'S3<select style="width: 100px; display: inline" name="FIELD[m]" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Helper::optionMonth(@x[1]) }}EOL'.
                'S3</select>EOL'.
                'S3<select style="width: 80px; display: inline" name="FIELD[y]" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Helper::optionRange(date(\'Y\')-5, date(\'Y\')+5, @x[0]) }}EOL'.
                'S3</select>EOL';
        elseif (strtolower($col['field'])=='year')
            $form = 'S3<select style="width: 80px" name="FIELD" id="FIELD" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Instance::optionRange(date(\'Y\')-5, date(\'Y\')+5, @POST.FIELD) }}EOL'.
                'S3</select>EOL';
        elseif (count($opt)) {
            $form = 'S4<option value=""> ---</option>EOL';
            foreach ($opt as $key => $value)
                $form .= str_replace(array(
                    'VALUE',
                    'LABEL',
                    ), array(
                    $value,
                    ucwords(str_replace('_', ' ', $value)),
                    ),
                    'S4<option value="FIELD"{{ @POST.FIELD==VALUE?'.
                    '" selected":"" }}> LABEL</option>EOL');
            $form = 'S3<select name="FIELD" id="FIELD" class="form-control"REQUIRED>EOL'.
                $form.
                'S3</select>EOL';
        } elseif ($isLongtext)
            $form = 'S3<textarea name="FIELD" id="FIELD" class="form-control"REQUIRED>'.
                '{{ @POST.FIELD }}</textarea>EOL';
        else
            $form = 'S3<input type="text" name="FIELD" id="FIELD" class="form-control"'.
                ' value="{{ @POST.FIELD }}"REQUIRED>EOL';

        return str_replace(array(
            'S1',
            'S2',
            'S3',
            'S4',
            'EOL',
            'FOR',
            'FIELD',
            'REQUIRED',
            ), array(
            $this->space($space),
            $this->space($space*2),
            $this->space($space*3),
            $this->space($space*4),
            $eol,
            (($isRadio || $isDate)?'':' for="'.$col['Field'].'"'),
            $col['Field'],
            ($col['Null']=='NO'?' required':''),
            ),
            'S1<div class="form-group">EOL'.
            'S2<labelFOR class="col-md-3">{{ @fields.FIELD }}</label>EOL'.
            'S2<div class="col-md-9">EOL'.
            $form.
            'S2</div>EOL'.
            'S1</div>');
    }

    private function space($length = 4)
    {
        return str_repeat(' ', $length);
    }

    private function filterEmpty($var)
    {
        return !(is_null($var) || false===$var || (is_array($var) && !$var));
    }

    private function token($token, $value)
    {
        $this->token['{#'.$token.'#}'] = $value;
    }

    private function tokenGet($token)
    {
        return $this->token['{#'.$token.'#}'];
    }

    private function pageTitle($prepend, $hypen = true)
    {
        Instance::set('pageTitle', $prepend.' '.($hypen?'- ':'').Instance::get('pageTitle'));
    }

    public function afterroute($moe)
    {
        $moe->set('rendertime', number_format(microtime(true)-$moe->get('TIME'), 5));
    }

    public function __construct($moe)
    {
        $moe->copy('app.name', 'pageTitle');
        $moe->set('menu', [
            'init'=>'Init Generator',
            'howto'=>'How To',
            ]);
        $moe->set('external', [
            'https://github.com/eghojansu/moe'=>'eghojansu/moe',
            'http://fatfreeframework.com'=>'Fatfree Framework',
            'https://github.com'=>'Github',
            'http://packagist.org'=>'Packagist',
            ]);
        foreach (explode('|', self::TOKEN) as $value)
            $this->token['{#'.$value.'#}'] = '';
        $basepath          = $moe->fixslashes(dirname(__DIR__)).'/';
        $this->config_path = $basepath.'config/';
        foreach ($this->config['path'] as $key => $value)
            $this->config['path'][$key]   = $basepath.$key;
        $this->config['database']['name'] = 'test_moe';
        $this->config['route_prefix']     = '/admin/';
        $this->config['cnamespace']       = 'app\\controller';
        $this->config['mnamespace']       = 'app\\model';

        $this->config['template']['controller'] = <<<STR
<?php

namespace {#cnamespace#};

use app\component\AbstractAdmin;
use {#mnamespace#}\{#model#};

class {#controller#} extends AbstractAdmin
{
    {#content#}
}
STR;
        $this->config['template']['model'] = <<<STR
<?php

namespace {#mnamespace#};

use moe\AbstractModel;
use moe\Instance;

class {#model#} extends AbstractModel
{
    {#content#}
}
STR;
        $this->config['template']['view_list'] = <<<STR
<h1 class="page-header">Data {#model#}</h1>
{#content#}
STR;
        $this->config['template']['view_input'] = <<<STR
<h1 class="page-header">
    Data {#model#}
    <small>input data</small>
</h1>
{#content#}
STR;
    }
}
