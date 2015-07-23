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
            $model          = ucfirst($moe->camelcase($table));
            $controller     = 'Crud'.$model;
            $queryC         = $db->query('show columns from '.$table);
            $columns_header = $columns_select = $fields_form = '';
            $schema = $pk   = array();
            while ($def = $queryC->fetch(PDO::FETCH_OBJ)) {
                $field           = ucwords(str_replace('_',' ', $def->Field));
                $columns_header .= '<th>'.$field.'</th>'.$eol;
                $columns_select .= $def->Field.','.$eol;
                $fields_form    .= $this->form((array) $def, $field).$eol;
                $schema[]        = $this->schema($def, $field);
                $def->Key != 'PRI'   || $pk[] = $def->Field;
            }

            $s3 = $this->space(4*3);
            $this->token('controller',     $controller);
            $this->token('model',          $model);
            $this->token('table',          $table);
            $this->token('columns_header', rtrim($columns_header));
            $this->token('columns_select', rtrim($columns_select, ','.$eol));
            $this->token('fields_form',    rtrim($fields_form));
            $this->token('schema',         "array(\n".$s3.
                implode(",\n".$s3, $schema).")");
            $this->token('primary_keys',   $moe->stringify($pk));
            $this->token('primary_key',    array_shift($pk));
            $this->token('relation',       $this->relation($table, $db));

            ++$i;
            foreach ($temp as $key => $value)
                $crud[$i][$key] = array(
                    'file'=>((isset($this->config['path'][$key])?
                        $this->config['path'][$key]:'').
                        ($key=='controller'?$controller.$php:
                            ($key=='model'?$model.$php:
                                (strpos($key, 'view')==0?$this->config['path']['view'].$table.'/'.
                                    $table.'_'.explode('_', $key)[1].$html:'xx')))),
                    'content'=>str_replace(
                        array_keys($this->token),
                        array_values($this->token),
                        $value));
            $routes[] = array_pop($crud[$i])['content'];
        }
        $moe->set('POST.cruds', $crud);
        $moe->set('POST.route', array(
            'file'=>$this->config['path']['config'].'crud.route.ini',
            'content'=>implode($eol, $routes)));
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
                if (file_exists($route = $moe->fixslashes($def['file'])) &&
                    !isset($def['overwrite']) && !$def['overwrite']
                )
                    array_push($skipped, $route);
                else {
                    $dir = substr($route, 0, strrpos($route, '/'));
                    file_exists($dir) || mkdir($dir,Base::MODE,true);
                    if ($moe->write($route, $def['content']))
                        array_push($success, $route);
                    else
                        array_push($failed, $route);
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

    private function relation($table, $db)
    {
        $query = $db->query('show create table '.$table);
        $structure = $query->fetch(PDO::FETCH_NUM)[1];
        unset($db, $query);
        $relation = '';
        $eol      = "\n";
        $s3       = $this->space(4*3);
        if (preg_match_all('/FOREIGN KEY \(\W*(?<fil1>[a-zA-Z_]+)\W*\) REFERENCES \W*(?<tab>[a-zA-Z_]+)\W* \(\W*(?<fil2>[a-zA-Z_]+)\W*\)/',
            $structure, $match, PREG_SET_ORDER))
            foreach ($match as $tab)
                $relation .= str_replace(
                    array('{tab}', '{fil1}', '{fil2}', '{EOL}', '{S3}'),
                    array($tab['tab'], $tab['fil1'], $tab['fil2'], $eol, $s3),
                    "'{tab}'=>'join {join} on {join}.{fil2} = {table}.{fil1}',{EOL}{S3}");
        return 'array('.($relation?$eol.$s3.$relation:'').')';
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

    private function schema($col, $field)
    {
        $col = (array) $col;
        return "array('$field', ".$this->filter($col).", ".$this->def($col).")";
    }

    private function filter(array $col)
    {
        $filter = "'trim'";
        $col['Null']=='YES' || $filter .= ",'required'";
        $length = 0;
        if (preg_match('/\((?<len>.*)\)/', $col['Type'], $match))
            $length = is_numeric($match['len'])? $match['len']:
                explode(',', str_replace(array('"', "'"), '', $match['len']));
        (is_array($length) || !$length || $length == 1) ||
            $filter .= ",'max_length'=>$length";
        (is_array($length) || !$length || $length > 1) || $filter .= ",'in_array'=>array(0,1)";
        !is_array($length) || $filter .= ",'in_array'=>".Instance::stringify($length);
        return "array($filter)";
    }

    private function form(array $col, $field)
    {
        $isNumber = $this->isNumber($col['Type']);
        $isRadio = false;
        $type = 'textarea';
        $opt = array();
        if (preg_match('/\((?<num>\d+)\)/', $col['Type'], $match)) {
            $type = 'text';
            if ($isNumber && $match['num']==1) {
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
                    'S3',
                    'S4',
                    'EOL',
                    'FIELD',
                    'VALUE',
                    'LABEL',
                    ), array(
                    $this->space($space*3),
                    $this->space($space*4),
                    $eol,
                    $col['Field'],
                    ($isNumber?$key:$value),
                    $value,
                    ),
                    'S3<label class="radio-inline">EOL'.
                    'S4<input type="radio" name="FIELD" value="VALUE"'.
                    '{{ @POST.FIELD==VALUE?" checked":"" }}REQUIRED> LABELEOL'.
                    'S3</label>EOL');
        elseif (count($opt)) {
            $form .= 'S4<option value=""> ---</option>EOL';
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
            $form = str_replace(array(
                'S3',
                'S4',
                'EOL',
                'FIELD',
                ), array(
                $this->space($space*3),
                $this->space($space*4),
                $eol,
                $col['Field'],
                ),
                'S3<select name="FIELD" id="FIELD" class="form-control"REQUIRED>EOL'.
                $form.
                'S3</select>EOL');
        } elseif ($type == 'textarea')
            $form = str_replace(array(
                'S3',
                'EOL',
                'FIELD',
                ), array(
                $this->space($space*3),
                $eol,
                $col['Field'],
                ),
                'S3<textarea name="FIELD" id="FIELD" class="form-control"REQUIRED>'.
                '{{ @POST.FIELD }}</textarea>EOL');
        else
            $form = str_replace(array(
                'S3',
                'EOL',
                'FIELD',
                ), array(
                $this->space($space*3),
                $eol,
                $col['Field'],
                ),
                'S3<input type="text" name="FIELD" id="FIELD" class="form-control"'.
                ' value="{{ @POST.FIELD }}"REQUIRED>EOL');

        return str_replace(array(
            'S1',
            'S2',
            'EOL',
            'FOR',
            'FIELD',
            'REQUIRED',
            ), array(
            $this->space($space),
            $this->space($space*2),
            $eol,
            ($isRadio?'':' for="'.$col['Field'].'"'),
            $field,
            ($col['Null']=='NO'?' required':''),
            ),
            'S1<div class="form-group">EOL'.
            'S2<labelFOR class="col-md-3">FIELD</label>EOL'.
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
