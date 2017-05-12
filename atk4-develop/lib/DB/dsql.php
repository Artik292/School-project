<?php
/**
 * Implementation of SQL Query Abstraction Layer for Agile Toolkit.
 */
class DB_dsql extends AbstractModel implements Iterator
{
    /**
     * Data accumulated by calling Definition methods, which is then
     * used when rendering.
     *
     * @var array
     */
    public $args = array();

    /**
     * List of PDO parametical arguments for a query. Used only during rendering.
     *
     * @var array
     */
    public $params = array();

    /**
     * Manually-specified params
     *
     * @var array
     */
    public $extra_params = array();

    /**
     * PDO statement if query is prepared. Used by iterator.
     *
     * @var PDOStatement
     */
    public $stmt = null;

    /**
     * Expression to use when converting to string
     *
     * @var string
     */
    public $template = null;

    /**
     * You can switch mode with select(), insert(), update() commands.
     * Mode is initialized to "select" by default.
     *
     * @var string
     */
    public $mode = null;

    /**
     * Used to determine main table.
     *
     * @var boolean|string
     */
    public $main_table = null;

    /**
     * If no fields are defined, this field is used.
     *
     * @var string|DB_dsql
     */
    public $default_field = '*';

    /**
     * Class name of default exception
     *
     * @var string
     */
    public $default_exception = 'Exception_DB';

    /**
     * Call $q->debug() to turn on debugging or $q->debug(false) to turn ir off.
     *
     * @var boolean
     */
    public $debug = false;

    /**
     * Prefix for all parameteric variables: a, a_2, a_3, etc.
     *
     * @var string
     */
    public $param_base = 'a';

    /**
     * When you convert this object to string, the following happens:
     *
     * @var string
     */
    public $output_mode = 'getOne';

    /**
     * Backtics are added around all fields. Set this to blank string to avoid.
     *
     * @var string
     */
    public $bt = '`';

    /**
     * Templates are used to construct most common queries. Templates may be
     * changed in vendor-specific implementation of dsql (extending this class).
     *
     * @var array Array of templates
     */
    public $sql_templates = array(
        'select' => 'select [options] [field] [from] [table] [join] [where] [group] [having] [order] [limit]',
        'insert' => 'insert [options_insert] into [table_noalias] ([set_fields]) values ([set_values])',
        'replace' => 'replace [options_replace] into [table_noalias] ([set_fields]) values ([set_values])',
        'update' => 'update [table_noalias] set [set] [where]',
        'delete' => 'delete from  [table_noalias] [where]',
        'truncate' => 'truncate table [table_noalias]',
        'describe' => 'desc [table_noalias]',
    );
    /**
     * Required for non-id based tables.
     *
     * @var string
     */
    public $id_field;

    /**
     * @var boolean
     */
    private $to_stringing = false;

    /** @var DB Owner of this object */
    public $owner;



    // {{{ Generic routines
    public function _unique(&$array, $desired = null)
    {
        $desired = preg_replace('/[^a-zA-Z0-9:]/', '_', $desired);
        $desired = parent::_unique($array, $desired);

        return $desired;
    }

    public function __clone()
    {
        $this->stmt = null;
    }

    /**
     * Convert object to string - generate SQL expression
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->to_stringing) {
            return 'Recursive __toString';
        }
        $this->to_stringing = true;
        try {
            if ($this->output_mode === 'render') {
                $output = $this->render();
            } else {
                $output = (string) $this->getOne();
            }
            $this->to_stringing = false;

            return $output;
        } catch (Exception $e) {
            $this->app->caughtException($e);
            //return "Exception: ".$e->getMessage();
        }

        $output = $this->__toString();
        $this->to_stringing = false;

        return $output;
    }

    /**
     * Explicitly sets template to your query. Remember to change
     * $this->mode if you switch this.
     *
     * @param string $template New template to use by render
     *
     * @return $this
     */
    public function template($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Change prefix for parametric values. Not really useful.
     *
     * @param string $param_base prefix to use for param names
     *
     * @return $this
     */
    public function paramBase($param_base)
    {
        $this->param_base = $param_base;

        return $this;
    }

    /**
     * Create new dsql object linked with the same database connection and
     * bearing same data-type. You can use this method to create sub-queries.
     *
     * @return DB_dsql Empty query for same database
     */
    public function dsql()
    {
        return $this->owner->dsql(get_class($this));
    }

    /**
     * Converts value into parameter and returns reference. Use only during
     * query rendering. Consider using `consume()` instead.
     *
     * @param string $val String literal containing input data
     *
     * @return string Safe and escapeed string
     */
    public function escape($val)
    {
        if ($val === UNDEFINED) {
            return '';
        }
        if (is_array($val)) {
            $out = array();
            foreach ($val as $v) {
                $out[] = $this->escape($v);
            }

            return $out;
        }
        $name = ':'.$this->param_base;
        $name = $this->_unique($this->params, $name);
        $this->params[$name] = $val;

        return $name;
    }

    /**
     * Recursively renders sub-query or expression, combining parameters.
     * If the argument is more likely to be a field, use tick=true.
     *
     * @param string|Field|DB_dsql $dsql Expression
     * @param bool                 $tick Preferred quoted style
     *
     * @return string Quoted expression
     */
    public function consume($dsql, $tick = true)
    {
        if ($dsql === UNDEFINED) {
            return '';
        }
        if ($dsql === null) {
            return '';
        }
        if (is_object($dsql) && $dsql instanceof Field) {
            $dsql = $dsql->getExpr();
        }
        if (!is_object($dsql) || !$dsql instanceof self) {
            return $tick ? $this->bt($dsql) : $dsql;
        }
        $dsql->params = &$this->params;
        $ret = $dsql->_render();
        if ($dsql->mode === 'select') {
            $ret = '('.$ret.')';
        }
        unset($dsql->params);
        $dsql->params = array();

        return $ret;
    }

    /**
     * Defines a custom tag variable. WARNING: always backtick / escaped
     * argument if it's unsafe.
     *
     * @param string|array  $tag   Corresponds to [tag] inside template
     * @param string|object $value Value for the template tag
     *
     * @return $this
     */
    public function setCustom($tag, $value = null)
    {
        if (is_array($tag)) {
            foreach ($tag as $key => $val) {
                $this->setCustom($key, $val);
            }

            return $this;
        }
        $this->args['custom'][$tag] = $value;

        return $this;
    }

    /**
     * This is identical to AbstractObject::debug(), but as an object
     * will refer to the $owner. This is to avoid string-casting and
     * messing up with the DSQL string.
     *
     * @param bool|string $msg "true" to start debugging
     */
    public function debug($msg = true)
    {
        if (is_bool($msg)) {
            $this->debug = $msg;

            return $this;
        }

        if (is_object($msg)) {
            throw $this->exception('Do not debug objects');
        }

        // The rest of this method is obsolete
        if ((isset($this->debug) && $this->debug)
            || (isset($this->app->debug) && $this->app->debug)
        ) {
            $this->app->outputDebug($this->owner, $msg);
        }
    }

    /**
     * Removes definition for argument. $q->del('where'), $q->del('fields') etc.
     *
     * @param string $args Could be 'field', 'where', 'order', 'limit', etc
     *
     * @return $this
     */
    public function del($args)
    {
        $this->args[$args] = array();

        return $this;
    }

    /**
     * Removes all definitions. Start from scratch.
     *
     * @return $this
     */
    public function reset()
    {
        $this->args = array();

        return $this;
    }
    // }}}

    // {{{ Dynamic Query Definition methods

    // {{{ Generic methods
    /**
     * Returns new dynamic query and initializes it to use specific template.
     *
     * @param string $expr SQL Expression. Don't pass unverified input
     * @param array  $tags Array of tags and values. @see setCustom()
     *
     * @return DB_dsql New dynamic query, won't affect $this
     */
    public function expr($expr, $tags = array())
    {
        return $this->dsql()->useExpr($expr, $tags);
    }

    /**
     * Change template of existing query instead of creating new one. If unsure
     * use expr().
     *
     * @param string $expr SQL Expression. Don't pass unverified input
     * @param array  $tags Obsolete, use templates / setCustom()
     *
     * @return $this
     */
    public function useExpr($expr, $tags = array())
    {
        foreach ($tags as $key => $value) {
            if ($key[0] == ':') {
                $this->extra_params[$key] = $value;
                continue;
            }

            $this->args['custom'][$key] = $value;
        }

        $this->template = $expr;
        if (!empty($tags)) {
            $this->setCustom($tags);
        }
        $this->output_mode = 'render';

        return $this;
    }

    /**
     * Shortcut to produce expression which concatinates "where" clauses with
     * "OR" operator.
     *
     * @return DB_dsql New dynamic query, won't affect $this
     */
    public function orExpr()
    {
        return $this->expr('([orwhere])');
    }

    /**
     * Shortcut to produce expression for series of conditions concatinated
     * with "and". Useful to be passed inside where() or join().
     *
     * @return DB_dsql New dynamic query, won't affect $this
     */
    public function andExpr()
    {
        return $this->expr('([andwhere])');
    }

    /**
     * Return expression containing a properly escaped field. Use make
     * subquery condition reference parent query.
     *
     * @param string $fld Field in SQL table
     *
     * @return DB_dsql Expression pointing to specified field
     */
    public function getField($fld)
    {
        if ($this->main_table === false) {
            throw $this->exception(
                'Cannot use getField() when multiple tables are queried'
            );
        }

        return $this->expr(
            $this->bt($this->main_table).
            '.'.
            $this->bt($fld)
        );
    }
    // }}}
    // {{{ table()
    /**
     * Specifies which table to use in this dynamic query. You may specify
     * array to perform operation on multiple tables.
     *
     * Examples:
     *  $q->table('user');
     *  $q->table('user','u');
     *  $q->table('user')->table('salary')
     *  $q->table(array('user','salary'));
     *  $q->table(array('user','salary'),'user');
     *  $q->table(array('u'=>'user','s'=>'salary'));
     *  $q->table($q2->table('user')->where('active',1), 'active_users');
     *
     * If you specify multiple tables, you still need to make sure to add
     * proper "where" conditions. All the above examples return $q (for chaining)
     *
     * You can also call table without arguments, which will return current table:
     *
     *  echo $q->table();
     *
     * If multiple tables are used, "false" is returned. Return is not quoted.
     * Please avoid using table() without arguments as more tables may be
     * dynamically added later.
     *
     * @param string|DB_dsql $table Specify table to use or DSQL to use as derived table
     * @param string $alias Specify alias for the table, if $table is DSQL, then alias is mandatory
     *
     * @return $this|string
     */
    public function table($table = UNDEFINED, $alias = UNDEFINED)
    {
        if ($table === UNDEFINED) {
            return $this->main_table;
        }

        if (is_array($table)) {
            foreach ($table as $alias => $t) {
                if (is_numeric($alias)) {
                    $alias = UNDEFINED;
                }
                $this->table($t, $alias);
            }

            return $this;
        }

        // main_table tracking allows us to
        if ($this->main_table === null) {
            $this->main_table = $alias === UNDEFINED || !$alias ? $table : $alias;
        } elseif ($this->main_table) {
            $this->main_table = false;   // query from multiple tables
        }

        // if $table is DSQL, then alias is mandatory
        if ($table instanceof DB_dsql && ($alias === UNDEFINED || !$alias)) {
            throw $this->exception('If table is passed as DSQL, then table alias is mandatory!');
        }
        
        $this->args['table'][] = array($table, $alias);

        return $this;
    }

    /**
     * Renders part of the template: [table]
     * Do not call directly.
     *
     * @return string Parsed template chunk
     */
    public function render_table()
    {
        $ret = array();
        if (!is_array($this->args['table'])) {
            return;
        }
        foreach ($this->args['table'] as $row) {
            list($table, $alias) = $row;

            if (is_string($table)) {
                // table name passed as string
                $table = $this->bt($table);

            } elseif ($table instanceof DB_dsql) {
                // table passed as DSQL expression

                // remove SQL_CALC_FOUND_ROWS from subquery
                $i = @array_search('SQL_CALC_FOUND_ROWS', $table->args['options']);
                if ($i !== false) {
                    unset($table->args['options'][$i]);
                }

                // consume subquery
                $table = $this->consume($table);
            }

            if ($alias !== UNDEFINED && $alias) {
                $table .= ' '.$this->bt($alias);
            }

            $ret[] = $table;
        }

        return implode(',', $ret);
    }

    /**
     * Conditionally returns "from", only if table is Specified
     * Do not call directly.
     *
     * @return string Parsed template chunk
     */
    public function render_from()
    {
        if ($this->args['table']) {
            return 'from';
        }

        return '';
    }

    /**
     * Returns template component [table_noalias].
     *
     * @return string Parsed template chunk
     */
    public function render_table_noalias()
    {
        $ret = array();
        foreach ($this->args['table'] as $row) {
            list($table, $alias) = $row;

            $table = $this->bt($table);

            $ret[] = $table;
        }

        return implode(', ', $ret);
    }
    // }}}
    // {{{ field()
    /**
     * Adds new column to resulting select by querying $field.
     *
     * Examples:
     *  $q->field('name');
     *
     * Second argument specifies table for regular fields
     *  $q->field('name','user');
     *  $q->field('name','user')->field('line1','address');
     *
     * Array as a first argument will specify mulitple fields, same as calling field() multiple times
     *  $q->field(array('name','surname'));
     *
     * Associative array will assume that "key" holds the alias. Value may be object.
     *  $q->field(array('alias'=>'name','alias2'=>surname'));
     *  $q->field(array('alias'=>$q->expr(..), 'alias2'=>$q->dsql()->.. ));
     *
     * You may use array with aliases together with table specifier.
     *  $q->field(array('alias'=>'name','alias2'=>surname'),'user');
     *
     * You can specify $q->expr() for calculated fields. Alias is mandatory.
     *  $q->field( $q->expr('2+2'),'alias');                // must always use alias
     *
     * You can use $q->dsql() for subqueries. Alias is mandatory.
     *  $q->field( $q->dsql()->table('x')... , 'alias');    // must always use alias
     *
     * @param string|array $field Specifies field to select
     * @param string       $table Specify if not using primary table
     * @param string       $alias Specify alias for this field
     *
     * @return $this
     */
    public function field($field, $table = null, $alias = null)
    {
        if (is_string($field) && strpos($field, ',') !== false) {
            $field = explode(',', $field);
        } elseif (is_object($field)) {
            $alias = $table;
            $table = null;
        }

        if (is_array($field)) {
            foreach ($field as $alias => $f) {
                if (is_numeric($alias)) {
                    $alias = null;
                }
                $this->field($f, $table, $alias);
            }

            return $this;
        }
        $this->args['fields'][] = array($field, $table, $alias);

        return $this;
    }

    /**
     * Removes all field definitions and returns only field you specify
     * as parameter to this method. Original query is not affected ($this)
     * Same as for field() syntax.
     *
     * @param string|array $field Specifies field to select
     * @param string       $table Specify if not using primary table
     * @param string       $alias Specify alias for this field
     *
     * @return DB_dsql Clone of $this with only one field
     */
    public function fieldQuery($field, $table = null, $alias = null)
    {
        $q = clone $this;

        return $q->del('fields')->field($field, $table, $alias);
    }

    /**
     * Returns template component [field].
     *
     * @return string Parsed template chunk
     */
    public function render_field()
    {
        $result = array();
        if (!$this->args['fields']) {
            if ($this->default_field instanceof self) {
                return $this->consume($this->default_field);
            }

            return (string) $this->default_field;
        }
        foreach ($this->args['fields'] as $row) {
            list($field, $table, $alias) = $row;
            if ($alias === $field) {
                $alias = UNDEFINED;
            }
            /**/$this->app->pr->start('dsql/render/field/consume');
            $field = $this->consume($field);
            /**/$this->app->pr->stop();
            if (!$field) {
                $field = $table;
                $table = UNDEFINED;
            }
            if ($table && $table !== UNDEFINED) {
                $field = $this->bt($table).'.'.$field;
            }
            if ($alias && $alias !== UNDEFINED) {
                $field .= ' '.$this->bt($alias);
            }
            $result[] = $field;
        }

        return implode(',', $result);
    }
    // }}}
    // {{{ where() and having()
    /**
     * Adds condition to your query.
     *
     * Examples:
     *  $q->where('id',1);
     *
     * Second argument specifies table for regular fields
     *  $q->where('id>','1');
     *  $q->where('id','>',1);
     *
     * You may use expressions
     *  $q->where($q->expr('a=b'));
     *  $q->where('date>',$q->expr('now()'));
     *  $q->where($q->expr('length(password)'),'>',5);
     *
     * Finally, subqueries can also be used
     *  $q->where('foo',$q->dsql()->table('foo')->field('name'));
     *
     * To specify OR conditions
     *  $q->where($q->orExpr()->where('a',1)->where('b',1));
     *
     * you can also use the shortcut:
     *
     *  $q->where(array('a is null','b is null'));
     *
     * @param mixed  $field Field, array for OR or Expression
     * @param string $cond  Condition such as '=', '>' or 'is not'
     * @param string $value Value. Will be quoted unless you pass expression
     * @param string $kind  Do not use directly. Use having()
     *
     * @return $this
     */
    public function where($field, $cond = UNDEFINED, $value = UNDEFINED, $kind = 'where')
    {
        if (is_array($field)) {
            // or conditions
            $or = $this->orExpr();
            foreach ($field as $row) {
                if (is_array($row)) {
                    $or->where(
                        $row[0],
                        array_key_exists(1, $row) ? $row[1] : UNDEFINED,
                        array_key_exists(2, $row) ? $row[2] : UNDEFINED
                    );
                } elseif (is_object($row)) {
                    $or->where($row);
                } else {
                    $or->where($or->expr($row));
                }
            }
            $field = $or;
        }

        if (is_string($field) && !preg_match('/^[.a-zA-Z0-9_]*$/', $field)) {
            // field contains non-alphanumeric values. Look for condition
            preg_match(
                '/^([^ <>!=]*)([><!=]*|( *(not|is|in|like))*) *$/',
                $field,
                $matches
            );
            $value = $cond;
            $cond = $matches[2];
            if (!$cond) {
                // IF COMPAT
                $matches[1] = $this->expr($field);
                if ($value && $value !== UNDEFINED) {
                    $cond = '=';
                } else {
                    $cond = UNDEFINED;
                }
            }
            $field = $matches[1];
        }

        $this->args[$kind][] = array($field, $cond, $value);

        return $this;
    }

    /**
     * Same syntax as where().
     *
     * @param mixed  $field Field, array for OR or Expression
     * @param string $cond  Condition such as '=', '>' or 'is not'
     * @param string $value Value. Will be quoted unless you pass expression
     *
     * @return $this
     */
    public function having($field, $cond = UNDEFINED, $value = UNDEFINED)
    {
        return $this->where($field, $cond, $value, 'having');
    }

    /**
     * Subroutine which renders either [where] or [having].
     *
     * @param string $kind 'where' or 'having'
     *
     * @return array Parsed chunks of query
     */
    public function _render_where($kind)
    {
        $ret = array();
        foreach ($this->args[$kind] as $row) {
            list($field, $cond, $value) = $row;

            if (is_object($field)) {
                // if first argument is object, condition must be explicitly
                // specified
                $field = $this->consume($field);
            } else {
                list($table, $field) = explode('.', $field, 2);
                if ($field) {
                    if ($this->mode == 'delete') {
                        $field = $this->bt($field);
                    } else {
                        $field = $this->bt($table).'.'.$this->bt($field);
                    }
                } else {
                    $field = $this->bt($table);
                }
            }

            // no value or condition passed, so this should be SQL chunk itself
            if ($value === UNDEFINED && $cond === UNDEFINED) {
                $r = $field;
                $ret[] = $r;
                continue;
            }

            // if no condition defined - set default condition
            if ($value === UNDEFINED) {
                $value = $cond;
                if (is_array($value)) {
                    $cond = 'in';
                } elseif (is_object($value) && @$value->mode === 'select') {
                    $cond = 'in';
                } else {
                    $cond = '=';
                }
            } else {
                $cond = trim(strtolower($cond));
            }

            // special conditions if value is null
            if ($value === null) {
                if ($cond === '=') {
                    $cond = 'is';
                } elseif (in_array($cond, array('!=', '<>', 'not'))) {
                    $cond = 'is not';
                }
            }

            // value should be array for such conditions
            if (($cond === 'in' || $cond === 'not in') && is_string($value)) {
                $value = explode(',', $value);
            }

            // if value is array, then use IN or NOT IN as condition
            if (is_array($value)) {
                $v = array();
                foreach ($value as $vv) {
                    $v[] = $this->escape($vv);
                }
                $value = '('.implode(',', $v).')';
                $cond = in_array($cond, array('!=', '<>', 'not', 'not in')) ? 'not in' : 'in';
                $r = $this->consume($field).' '.$cond.' '.$value;
                $ret[] = $r;
                continue;
            }

            // if value is object, then it should be DSQL itself
            // otherwise just escape value
            if (is_object($value)) {
                $value = $this->consume($value);
            } else {
                $value = $this->escape($value);
            }

            $r = $field.' '.$cond.' '.$value;
            $ret[] = $r;
        }

        return $ret;
    }

    /**
     * Renders [where].
     *
     * @return string rendered SQL chunk
     */
    public function render_where()
    {
        if (!$this->args['where']) {
            return;
        }

        return 'where '.implode(' and ', $this->_render_where('where'));
    }

    /**
     * Renders [orwhere].
     *
     * @return string rendered SQL chunk
     */
    public function render_orwhere()
    {
        if (!$this->args['where']) {
            return;
        }

        return implode(' or ', $this->_render_where('where'));
    }

    /**
     * Renders [andwhere].
     *
     * @return string rendered SQL chunk
     */
    public function render_andwhere()
    {
        if (!$this->args['where']) {
            return;
        }

        return implode(' and ', $this->_render_where('where'));
    }

    /**
     * Renders [having].
     *
     * @return string rendered SQL chunk
     */
    public function render_having()
    {
        if (!$this->args['having']) {
            return;
        }

        return 'having '.implode(' and ', $this->_render_where('having'));
    }
    // }}}
    // {{{ join()
    /**
     * Joins your query with another table.
     *
     * Examples:
     *  $q->join('address');         // on user.address_id=address.id
     *  $q->join('address.user_id'); // on address.user_id=user.id
     *  $q->join('address a');       // With alias
     *  $q->join(array('a'=>'address')); // Also alias
     *
     * Second argument may specify the field of the master table
     *  $q->join('address', 'billing_id');
     *  $q->join('address.code', 'code');
     *  $q->join('address.code', 'user.code');
     *
     * Third argument may specify which kind of join to use.
     *  $q->join('address', null, 'left');
     *  $q->join('address.code', 'user.code', 'inner');
     *
     * Using array syntax you can join multiple tables too
     *  $q->join(array('a'=>'address', 'p'=>'portfolio'));
     *
     * You can use expression for more complex joins
     *  $q->join('address',
     *      $q->orExpr()
     *          ->where('user.billing_id=address.id')
     *          ->where('user.technical_id=address.id')
     *  )
     *
     * @param string $foreign_table  Table to join with
     * @param mixed  $master_field   Field in master table
     * @param string $join_kind      'left' or 'inner', etc
     * @param string $_foreign_alias Internal, don't use
     *
     * @return $this
     */
    public function join(
        $foreign_table,
        $master_field = null,
        $join_kind = null,
        $_foreign_alias = null
    ) {
        // Compatibility mode
        if (isset($this->app->compat)) {
            if (strpos($foreign_table, ' ')) {
                list($foreign_table, $alias) = explode(' ', $foreign_table);
                $foreign_table = array($alias => $foreign_table);
            }
            if (strpos($master_field, '=')) {
                $master_field = $this->expr($master_field);
            }
        }

        // If array - add recursively
        if (is_array($foreign_table)) {
            foreach ($foreign_table as $alias => $foreign) {
                if (is_numeric($alias)) {
                    $alias = null;
                }

                $this->join($foreign, $master_field, $join_kind, $alias);
            }

            return $this;
        }
        $j = array();

        // Split and deduce fields
        list($f1, $f2) = explode('.', $foreign_table, 2);

        if (is_object($master_field)) {
            $j['expr'] = $master_field;
        } else {
            // Split and deduce primary table
            if (is_null($master_field)) {
                list($m1, $m2) = array(null, null);
            } else {
                list($m1, $m2) = explode('.', $master_field, 2);
            }
            if (is_null($m2)) {
                $m2 = $m1;
                $m1 = null;
            }
            if (is_null($m1)) {
                $m1 = $this->main_table;
            }

            // Identify fields we use for joins
            if (is_null($f2) && is_null($m2)) {
                $m2 = $f1.'_id';
            }
            if (is_null($m2)) {
                $m2 = 'id';
            }
            $j['m1'] = $m1;
            $j['m2'] = $m2;
        }
        $j['f1'] = $f1;
        if (is_null($f2)) {
            $f2 = 'id';
        }
        $j['f2'] = $f2;

        $j['t'] = $join_kind ?: 'left';
        $j['fa'] = $_foreign_alias;

        $this->args['join'][] = $j;

        return $this;
    }

    /**
     * Renders [join].
     *
     * @return string rendered SQL chunk
     */
    public function render_join()
    {
        if (!$this->args['join']) {
            return '';
        }
        $joins = array();
        foreach ($this->args['join'] as $j) {
            $jj = '';

            $jj .= $j['t'].' join ';

            $jj .= $this->bt($j['f1']);

            if (!is_null($j['fa'])) {
                $jj .= ' as '.$this->bt($j['fa']);
            }

            $jj .= ' on ';

            if ($j['expr']) {
                $jj .= $this->consume($j['expr']);
            } else {
                $jj .=
                    $this->bt($j['fa'] ?: $j['f1']).'.'.
                    $this->bt($j['f2']).' = '.
                    $this->bt($j['m1']).'.'.
                    $this->bt($j['m2']);
            }
            $joins[] = $jj;
        }

        return implode(' ', $joins);
    }
    // }}}
    // {{{ group()
    /**
     * Implemens GROUP BY functionality. Simply pass either string field
     * or expression.
     *
     * @param string|object $group Group by this
     *
     * @return $this
     */
    public function group($group)
    {
        return $this->_setArray($group, 'group');
    }

    /**
     * Renders [group].
     *
     * @return string rendered SQL chunk
     */
    public function render_group()
    {
        if (!$this->args['group']) {
            return'';
        }
        $x = array();
        foreach ($this->args['group'] as $arg) {
            $x[] = $this->consume($arg);
        }

        return 'group by '.implode(', ', $x);
    }
    // }}}
    // {{{ order()
    /**
     * Orders results by field or Expression. See documentation for full
     * list of possible arguments.
     *
     * $q->order('name');
     * $q->order('name desc');
     * $q->order('name desc, id asc')
     * $q->order('name',true);
     *
     * @param mixed $order Order by
     * @param string|bool $desc  true to sort descending
     *
     * @return $this
     */
    public function order($order, $desc = null)
    {
        // Case with comma-separated fields or first argument being an array
        if (is_string($order) && strpos($order, ',') !== false) {
            // Check for multiple
            $order = explode(',', $order);
        }
        if (is_array($order)) {
            if (!is_null($desc)) {
                throw $this->exception(
                    'If first argument is array, second argument must not be used'
                );
            }
            foreach (array_reverse($order) as $o) {
                $this->order($o);
            }

            return $this;
        }

        // First argument may contain space, to divide field and keyword
        if (is_null($desc) && is_string($order) && strpos($order, ' ') !== false) {
            list($order, $desc) = array_map('trim', explode(' ', trim($order), 2));
        }

        if (is_string($order) && strpos($order, '.') !== false) {
            $order = implode('.', $this->bt(explode('.', $order)));
        }

        if (is_bool($desc)) {
            $desc = $desc ? 'desc' : '';
        } elseif (strtolower($desc) === 'asc') {
            $desc = '';
        } elseif ($desc && strtolower($desc) != 'desc') {
            throw $this->exception('Incorrect ordering keyword')
                ->addMoreInfo('order by', $desc);
        }

        // TODO:
        /*
        if (isset($this->args['order'][0]) and (
            $this->args['order'][0] === array($order,$desc))) {
        }
         */
        $this->args['order'][] = array($order, $desc);

        return $this;
    }

    /**
     * Renders [order].
     *
     * @return string rendered SQL chunk
     */
    public function render_order()
    {
        if (!$this->args['order']) {
            return'';
        }
        $x = array();
        foreach ($this->args['order'] as $tmp) {
            list($arg, $desc) = $tmp;
            $x[] = $this->consume($arg).($desc ? (' '.$desc) : '');
        }

        return 'order by '.implode(', ', array_reverse($x));
    }
    // }}}
    // {{{ option() and args()
    /**
     * Defines query option, such as DISTINCT.
     *
     * @param string|expresion $option Option to put after SELECT
     *
     * @return $this
     */
    public function option($option)
    {
        return $this->_setArray($option, 'options');
    }

    /**
     * Renders [options].
     *
     * @return string rendered SQL chunk
     */
    public function render_options()
    {
        if (!isset($this->args['options'])) {
            return "";
        }
        return @implode(' ', $this->args['options']);
    }

    /**
     * Defines insert query option, such as IGNORE.
     *
     * @param string|expresion $option Option to put after INSERT
     *
     * @return $this
     */
    public function option_insert($option)
    {
        return $this->_setArray($option, 'options_insert');
    }

    /**
     * Defines replace query option, such as IGNORE.
     *
     * @param string|expresion $option Option to put after REPLACE
     *
     * @return $this
     */
    public function option_replace($option)
    {
        return $this->_setArray($option, 'options_replace');
    }

    /**
     * Renders [options_insert].
     *
     * @return string rendered SQL chunk
     */
    public function render_options_insert()
    {
        if (!$this->args['options_insert']) {
            return '';
        }

        return implode(' ', $this->args['options_insert']);
    }

    /**
     * Renders [options_replace].
     *
     * @return string rendered SQL chunk
     */
    public function render_options_replace()
    {
        if (!$this->args['options_replace']) {
            return '';
        }

        return implode(' ', $this->args['options_replace']);
    }
    // }}}
    // {{{  call() and function execution
    /**
     * Sets a template for a user-defined method call with specified arguments.
     *
     * @param string $fx   Name of the user defined method
     * @param array  $args Arguments in mixed form
     *
     * @return $this
     */
    public function call($fx, $args = null)
    {
        $this->mode = 'call';
        $this->args['fx'] = $fx;
        if (!is_null($args)) {
            $this->args($args);
        }
        $this->template = 'call [fx]([args])';

        return $this;
    }

    /**
     * Executes a standard function with arguments, such as IF.
     *
     * $q->fx('if', array($condition, $if_true, $if_false));
     *
     * @param string $fx   Name of the built-in method
     * @param array  $args Arguments
     *
     * @return $this
     */
    public function fx($fx, $args = null)
    {
        $this->mode = 'fx';
        $this->args['fx'] = $fx;
        if (!is_null($args)) {
            $this->args($args);
        }
        $this->template = '[fx]([args])';

        return $this;
    }

    /**
     * set arguments for call(). Used by fx() and call() but you can use This
     * with ->expr("in ([args])")->args($values);.
     *
     * @param array $args Array with mixed arguments
     *
     * @return $this
     */
    public function args($args)
    {
        return $this->_setArray($args, 'args', false);
    }

    /**
     * Renders [args].
     *
     * @return string rendered SQL chunk
     */
    public function render_args()
    {
        $x = array();
        foreach ($this->args['args'] as $arg) {
            $x[] = is_object($arg) ?
                $this->consume($arg) :
                $this->escape($arg);
        }

        return implode(', ', $x);
    }

    /**
     * Sets IGNORE option.
     *
     * @return $this
     */
    public function ignore()
    {
        $this->args['options_insert'][] = 'ignore';

        return $this;
    }

    /**
     * Check if specified option was previously added.
     *
     * @param string $option Which option to check?
     *
     * @return bool
     */
    public function hasOption($option)
    {
        return @in_array($option, $this->args['options']);
    }

    /**
     * Check if specified insert option was previously added.
     *
     * @param string $option Which option to check?
     *
     * @return bool
     */
    public function hasInsertOption($option)
    {
        return @in_array($option, $this->args['options_insert']);
    }
    // }}}
    // {{{ limit()
    /**
     * Limit how many rows will be returned.
     *
     * @param int $cnt   Number of rows to return
     * @param int $shift Offset, how many rows to skip
     *
     * @return $this
     */
    public function limit($cnt, $shift = 0)
    {
        $this->args['limit'] = array(
            'cnt' => $cnt,
            'shift' => $shift,
        );

        return $this;
    }

    /**
     * Renders [limit].
     *
     * @return string rendered SQL chunk
     */
    public function render_limit()
    {
        if ($this->args['limit']) {
            return 'limit '.
                (int) $this->args['limit']['shift'].
                ', '.
                (int) $this->args['limit']['cnt'];
        }
    }
    // }}}
    // {{{ set()
    /**
     * Sets field value for INSERT or UPDATE statements.
     *
     * @param string $field Name of the field
     * @param mixed  $value Value of the field
     *
     * @return $this
     */
    public function set($field, $value = UNDEFINED)
    {
        if ($value === false) {
            throw $this->exception('Value "false" is not supported by SQL')
                ->addMoreInfo('field', $field);
        }
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        }

        if ($value === UNDEFINED) {
            throw $this->exception('Specify value when calling set()');
        }

        $this->args['set'][$field] = $value;

        return $this;
    }
    /**
     * Renders [set] for UPDATE query.
     *
     * @return string rendered SQL chunk
     */
    public function render_set()
    {
        $x = array();
        if ($this->args['set']) {
            foreach ($this->args['set'] as $field => $value) {
                if (is_object($field)) {
                    $field = $this->consume($field);
                } else {
                    $field = $this->bt($field);
                }
                if (is_object($value)) {
                    $value = $this->consume($value);
                } else {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $value = $this->escape($value);
                }

                $x[] = $field.'='.$value;
            }
        }

        return implode(', ', $x);
    }
    /**
     * Renders [set_fields] for INSERT.
     *
     * @return string rendered SQL chunk
     */
    public function render_set_fields()
    {
        $x = array();
        if ($this->args['set']) {
            foreach ($this->args['set'] as $field => $value) {
                if (is_object($field)) {
                    $field = $this->consume($field);
                } else {
                    $field = $this->bt($field);
                }

                $x[] = $field;
            }
        }

        return implode(',', $x);
    }
    /**
     * Renders [set_values] for INSERT.
     *
     * @return string rendered SQL chunk
     */
    public function render_set_values()
    {
        $x = array();
        if ($this->args['set']) {
            foreach ($this->args['set'] as $field => $value) {
                if (is_object($value)) {
                    $value = $this->consume($value);
                } else {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $value = $this->escape($value);
                }

                $x[] = $value;
            }
        }

        return implode(',', $x);
    }
    // }}}
    // {{{ Miscelanious
    /**
     * Adds backtics around argument. This will allow you to use reserved
     * SQL words as table or field names such as "table".
     *
     * @param mixed $s Any string or array of strings
     *
     * @return string Quoted string
     */
    public function bt($s)
    {
        if (is_array($s)) {
            $out = array();
            foreach ($s as $ss) {
                $out[] = $this->bt($ss);
            }
            return $out;
        }

        if (!$this->bt
            || is_object($s)
            || $s === '*'
            || strpos($s, '.') !== false
            || strpos($s, '(') !== false
            || strpos($s, $this->bt) !== false
        ) {
            return $s;
        }

        return $this->bt.$s.$this->bt;
    }
    /**
     * Internal method which can be used by simple param-giving methods such
     * as option(), group(), etc.
     *
     * @param mixed  $values
     * @param string $name
     * @param bool   $parse_commas
     *
     * @private
     *
     * @return $this
     */
    public function _setArray($values, $name, $parse_commas = true)
    {
        if (is_string($values) && $parse_commas && strpos($values, ',')) {
            $values = explode(',', $values);
        }
        if (!is_array($values)) {
            $values = array($values);
        }
        if (!isset($this->args[$name])) {
            $this->args[$name] = array();
        }
        $this->args[$name] = array_merge($this->args[$name], $values);

        return $this;
    }
    // }}}

    // }}}

    // {{{ Statement templates and interfaces

    /**
     * Switch template for this query. Determines what would be done
     * on execute.
     *
     * By default it is in SELECT mode
     *
     * @param string $mode A key for $this->sql_templates
     *
     * @return $this
     */
    public function SQLTemplate($mode)
    {
        $this->mode = $mode;
        $this->template = $this->sql_templates[$mode];

        return $this;
    }
    /**
     * Return expression for concatinating multiple values
     * Accepts variable number of arguments, all of them would be
     * escaped.
     *
     * @return DB_dsql clone of $this
     */
    public function concat()
    {
        $t = clone $this;

        return $t->fx('concat', func_get_args());
    }

    /**
     * Creates a query for listing tables in databse-specific form
     * Agile Toolkit DSQL does not pretend to know anything about model
     * structure, so result parsing is up to you.
     *
     * @param string $table Table
     *
     * @return DB_dsql clone of $this
     */
    public function describe($table = null)
    {
        $q = clone $this;
        if ($table !== null) {
            $q->table($table);
        }

        return $q->SQLTemplate('describe');
    }

    /**
     * Renders [fx].
     *
     * @return string rendered SQL chunk
     */
    public function render_fx()
    {
        return $this->args['fx'];
    }

    /**
     * Creates expression for SUM().
     *
     * @param string|object $arg Typically fieldname or expression of a sub-query
     *
     * @return DB_dsql clone of $this
     */
    public function sum($arg)
    {
        return $this->expr('sum([sum])')->setCustom('sum', $this->bt($arg));
    }

    /**
     * Creates expression for COUNT().
     *
     * @param string|object $arg Typically fieldname or expression of a sub-query
     *
     * @return DB_dsql clone of $this
     */
    public function count($arg = null)
    {
        if (is_null($arg)) {
            $arg = '*';
        }

        return $this->expr('count([count])')->setCustom('count', $this->bt($arg));
    }
    /**
     * Returns method for generating random numbers. This is used for ordering
     * table in random order.
     *
     * @return DB_dsql clone of $this
     */
    public function random()
    {
        return $this->expr('rand()');
    }
    // }}}

    // {{{ More complex query generations and specific cases

    /**
     * Executes current query.
     *
     * @return $this
     */
    public function execute()
    {
        try {
            /**/$this->app->pr->start('dsql/execute/render');
            $q = $this->render();
            /**/$this->app->pr->next('dsql/execute/query');
            $this->stmt = $this->owner->query($q, $this->params);
            $this->template = $this->mode = null;
            /**/$this->app->pr->stop();

            return $this;
        } catch (PDOException $e) {
            throw $this->exception('Database Query Failed')
                ->addMoreInfo('pdo_error', $e->getMessage())
                ->addMoreInfo('mode', $this->mode)
                ->addMoreInfo('params', $this->params)
                ->addMoreInfo('query', $q)
                ->addMoreInfo('template', $this->template)
                ;
        }
    }

    /**
     * Executes select query.
     *
     * @return $this
     */
    public function select()
    {
        return $this->SQLTemplate('select')->execute();
    }

    /**
     * Executes insert query. Returns ID of new record.
     *
     * @return int new record ID (from last_id)
     */
    public function insert()
    {
        $this->SQLTemplate('insert')->execute();

        return
            $this->hasInsertOption('ignore') ? null :
            $this->owner->lastID();
    }

    /**
     * Inserts multiple rows of data. Uses ignore option
     * AVOID using this, might not be implemented correctly.
     *
     * @param array $array Insert multiple rows into table with one query
     *
     * @return array List of IDs
     */
    public function insertAll($array)
    {
        $ids = array();
        foreach ($array as $hash) {
            $ids[] = $this->del('set')->set($hash)->insert();
        }

        return $ids;
    }

    /**
     * Executes update query.
     *
     * @return $this
     */
    public function update()
    {
        return $this->SQLTemplate('update')->execute();
    }

    /**
     * Executes replace query.
     *
     * @return $this
     */
    public function replace()
    {
        return $this->SQLTemplate('replace')->execute();
    }

    /**
     * Executes delete query.
     *
     * @return $this
     */
    public function delete()
    {
        return $this->SQLTemplate('delete')->execute();
    }

    /**
     * Executes truncate query.
     *
     * @return $this
     */
    public function truncate()
    {
        return $this->SQLTemplate('truncate')->execute();
    }

    /**
     * @deprecated 4.3.0 use select()
     */
    public function do_select()
    {
        return $this->select();
    }
    /**
     * @deprecated 4.3.0 use insert()
     */
    public function do_insert()
    {
        return $this->insert();
    }
    /**
     * @deprecated 4.3.0  use update()
     */
    public function do_update()
    {
        return $this->update();
    }
    /**
     * @deprecated 4.3.0 use replace()
     */
    public function do_replace()
    {
        return $this->replace();
    }
    // }}}

    // {{{ Data fetching modes
    /**
     * Will execute DSQL query and return all results inside array of hashes.
     *
     * @return array Array of associative arrays
     */
    public function get()
    {
        if (!$this->stmt) {
            $this->execute();
        }
        $res = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->rewind();
        $this->stmt = null;

        return $res;
    }

    /**
     * Will execute DSQL query and return first column of a first row.
     *
     * You can also simply cast your DSQL into string to get this value
     *
     * echo $dsql;
     *
     * @return string Value of first column in first row
     */
    public function getOne()
    {
        $res = $this->getRow();
        $this->rewind();
        $this->stmt = null;

        return $res[0];
    }
    /**
     * Will execute DSQL query and return first row as array (not hash). If
     * you call several times will return subsequent rows.
     *
     * @return array Next row of your data (not hash)
     */
    public function getRow()
    {
        return $this->fetch(PDO::FETCH_NUM);
    }
    /**
     * Will execute DSQL query and return first row as hash (column=>value).
     *
     * @return array Hash of next row in data stream
     */
    public function getHash()
    {
        return $this->fetch(PDO::FETCH_ASSOC);
    }
    /**
     * Will execute the query (if it's not executed already) and return
     * first row.
     *
     * @param int $mode PDO fetch mode
     *
     * @return mixed return result of PDO::fetch
     */
    public function fetch($mode = PDO::FETCH_ASSOC)
    {
        if (!$this->stmt) {
            $this->execute();
        }

        return $this->stmt->fetch($mode);
    }
    // {{{ Obsolete functions
    /** @obsolete. Use get() */
    public function fetchAll()
    {
        return $this->get();
    }
    /** @obsolete. Use getQne() */
    public function do_getOne()
    {
        return $this->getOne();
    }
    /** @obsolete. Use get() */
    public function do_getAllHash()
    {
        return $this->get();
    }
    public function do_getAll()
    {
        return $this->get();
    }
    /** @obsolete. Use get() */
    public function getAll()
    {
        return $this->get();
    }
    /** @obsolete. Use getRow() */
    public function do_getRow()
    {
        return $this->getRow();
    }
    /** @obsolete. Use getHash() */
    public function do_getHash()
    {
        return $this->getHash();
    }
    // }}}

    /**
     * Sets flag to hint SQL (if supported) to prepare total number of columns.
     * Use foundRows() to read this afterwards.
     *
     * @return $this
     */
    public function calcFoundRows()
    {
        return $this;
    }

    /**
     * @deprecated 4.3.2. Naming bug. Use foundRows() instead.
     */
    public function calc_found_rows()
    {
        return $this->calcFoundRows();
    }
    /**
     * After fetching data, call this to find out how many rows there were in
     * total. Call calcFoundRows() for better performance.
     *
     * @return int number of results
     */
    public function foundRows()
    {
        if ($this->hasOption('SQL_CALC_FOUND_ROWS')) {
            return (int) $this->owner->getOne('select found_rows()');
        }
        /* db-compatible way: */
        $c = clone $this;
        $c->del('limit');

        return (int) $c->fieldQuery('count(*)')->getOne();
    }
    // }}}

    // {{{ Iterator support
    public $data = false;
    public $_iterating = false;
    public $preexec = false;
    /**
     * Execute query faster, but don't fetch data until iterating started. This
     * can be done if you need to know foundRows() before fetching data.
     *
     * @return $this
     */
    public function preexec()
    {
        $this->execute();
        $this->preexec = true;

        return $this;
    }
    public function rewind()
    {
        if ($this->_iterating) {
            $this->stmt = null;
            $this->_iterating = false;
        }
        $this->_iterating = true;

        return $this;
    }
    public function next()
    {
        $this->data = $this->fetch();

        return $this;
    }
    public function current()
    {
        return $this->data;
    }
    public function key()
    {
        return $this->data[$this->id_field];
    }
    public function valid()
    {
        if (!$this->stmt || $this->preexec) {
            $this->preexec = false;
            $this->data = $this->fetch();
        }

        return (boolean) $this->data;
    }
    // }}}

    // {{{ Rendering
    /**
     * Return formatted debug output.
     *
     * @param string $r Rendered material
     *
     * @return string SQL syntax of query
     */
    public function getDebugQuery($r = null)
    {
        if ($r === null) {
            $r = $this->_render();
        }

        $d = $r;
        $pp = array();
        $d = preg_replace('/`([^`]*)`/', '`<font color="black">\1</font>`', $d);
        foreach (array_reverse($this->params) as $key => $val) {
            if (is_string($val)) {
                $d = preg_replace('/'.$key.'([^_]|$)/', '"<font color="green">'.
                    htmlspecialchars(addslashes($val)).'</font>"\1', $d);
            } elseif (is_null($val)) {
                $d = preg_replace(
                    '/'.$key.'([^_]|$)/',
                    '<font color="black">NULL</font>\1',
                    $d
                );
            } elseif (is_numeric($val)) {
                $d = preg_replace(
                    '/'.$key.'([^_]|$)/',
                    '<font color="red">'.$val.'</font>\1',
                    $d
                );
            } else {
                $d = preg_replace('/'.$key.'([^_]|$)/', $val.'\1', $d);
            }

            $pp[] = $key;
        }

        return $d." <font color='gray'>[".
            implode(', ', $pp).']</font>';
    }
    /**
     * Converts query into string format. This will contain parametric
     * references.
     *
     * @return string Resulting query
     */
    public function render()
    {
        $this->params = $this->extra_params;
        $r = $this->_render();
        $this->debug((string) $this->getDebugQuery($r));

        return $r;
    }
    /**
     * Helper for render(), which does the actual work.
     *
     * @private
     *
     * @return string Resulting query
     */
    public function _render()
    {
        /**/$this->app->pr->start('dsql/render');
        if (is_null($this->template)) {
            $this->SQLTemplate('select');
        }
        $self = $this;
        $res = preg_replace_callback(
            '/\[([a-z0-9_]*)\]/',
            function ($matches) use ($self) {
                /**/$self->app->pr->next('dsql/render/'.$matches[1], true);
                $fx = 'render_'.$matches[1];
                if (isset($self->args['custom'][$matches[1]])) {
                    return $self->consume($self->args['custom'][$matches[1]], false);
                } elseif ($self->hasMethod($fx)) {
                    return $self->$fx();
                } else {
                    return $matches[0];
                }
            },
            $this->template
        );
        /**/$this->app->pr->stop(null, true);

        return $res;
    }
    // }}}
}
