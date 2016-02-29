<?php
require_once __DIR__.'/Database.php';

/**
 * Handle the interaction with the database for an specific object, it handle
 * the database CRUD for a specific table and object
 * @todo replace the arrays for objects
 */
class DatabaseHelper {
    /**
     * Ascend and descend constants
     */
    const ORDER_ASCEND = 'ASC';
    const ORDER_DESCEND = 'DESC';

    /**
     * Variable that indicates wheter should add the where clause or not
     * @var boolean $build_where
     */
    public $build_where = false;

    /**
     * Name of the class to be created
     * @var string $class_name
     */
    protected $class_name;

    /**
     * Name of the primary key column, used by the funcions update, and get
     * @var string $id_name
     */
    protected $id_name;

    /**
     * Indicates if paginate or not
     * @var boolean
     */
    public $limit;

    /**
     * Offset for the mysql limit
     * @var integer
     */
    public $offset;

    /**
     * Number of the current page to get
     * @var integer
     */
    public $page_number;

    /**
     * The keys for the arg used un the execute of the database
     * @var array
     */
    protected $prepare_keys = array('name', 'value', 'type');

    /**
     * Last query used, it should not be modifyed manually, insted user the
     * build functions to build the query
     * @var string $query
     */
    public $query;

    /**
     * Array of columns from which the funcdtion search will search
     * @var array[string]
     */
    protected $search_columns;

    /**
     * Array containing the columns of the table, this is retrieve from the
     * database and should not be fulled manually, in case of want to add/remove
     * or modify the values it shoud bw on the function retrieve values
     *
     * @var array $table_columns
     */
    protected $table_columns;

    /**
     * Array of the keys of the array $tablel_columns, since is used a lot more
     * than $table_columns is save as a proteted variable
     * @var array $table_keys
     */
    protected $table_keys;

    /**
     * Name of the table to do the insert
     * @var string $table_name
     */
    protected $table_name;

    /**
     * Total pages to retrieve the object
     * @var integer
     */
    protected $total_pages;

    /**
     * Array for the params to be executed in case of $build_where is enabled
     * @var array $where_params
     */
    protected $where_params;

    /**
     * Where clause to be executed
     * @var string $where_query
     */
    protected $where_query;

    /**
     * Assign the variables in the arguments, also retrieves the columns from
     * the database
     * @param string $table_name (See protected property $table_name)
     * @param string $class_name (See protected property $class_name)
     * @param string $id_name    (See protected property $id_name)
     */
    public function __construct($table_name = '', $class_name = '', $id_name = '') {
        /* Assign the variables */
        $this->table_name = $table_name;
        $this->class_name = $class_name;
        $this->id_name = $id_name;

        /* Retrieve the columns */
        $this->retrieveColumns();
    }

    /**
     * Instantiate the objects based the array given
     * @param  array $results results from a query, it needs to be a
     *         PDO::FETCH_ASSOC result
     *
     * @return array Array of objects
     */
    protected function build($results) {
        /* save the class name as a local variable to allow us to create an
         * object based on this name, we cannot do something like this:
         *
         *   $($this->class_name)::instantiate($temp);
         *
         *  so we save it in a single local variable */
        $class_name = $this->class_name;
        $return = [];
        foreach ($results as $temp) {
            $return[] = $class_name::instantiate($temp);
        }
        return $return;
    }

    /**
     * Build the query to be used to return the objects desired
     */
    protected function buildQuery() {
        $this->query = 'SELECT ';
        $this->query .= implode(', ', $this->getKeys());
        $this->query .= ' FROM '.$this->table_name;

        if ($this->build_where) {
            $this->query .= ' WHERE '.$this->where_query;
        }

        if (isset($this->order_column)) {
            $this->query .= ' ORDER BY ' . $this->order_column . ' ';
            $this->query .= $this->order_type;
        }

        if ($this->limit) {
            $this->query .= ' LIMIT :offset, ' . Database::$limit;
        }
    }

    /**
     * Execute the query build by buildQuery() and return the results
     * @return array PDO::fetchAll(PDO::FETCH_ASSOC)
     */
    protected function buildSQL() {
        global $database;
        $query = $this->getQuery();
        $args = array();

        if ($this->build_where) {
            foreach ($this->where_params as $param) {
                $args[] = $param;
            }
        }

        if ($this->limit) {
            $args[] = $this->getParamArray(':offset', $this->offset, PDO::PARAM_INT);
        }
        return $database->singleSelect($query, $args);
    }

    /**
     * Disable momentary the where clause to return all the elements of the
     * table
     * @return array PDOAtatement::fetchAll(PDO::FETCH_ASSOC)
     */
    protected function buildSQLAll() {
        $where = $this->build_where;
        $this->build_where = false;

        $sql = $this->buildSQL();

        $this->build_where = $where;
        return $sql;
    }

    /**
     * Get the total of the object instance, it connects to the database anf get
     * the total of rows in the desired table
     * @global PDOHandler $database
     * @return integer total of rows
     */
    public function count() {
        global $database;
        $args = [];
        $query = 'SELECT COUNT(*) as total FROM '.$this->table_name;

        /* Get the where query */
        if ($this->build_where) {
            $query .= ' WHERE '.$this->where_query;
            foreach ($this->where_params as $param) {
                $args[] = $param;
            }
        }

        $result = $database->singleSelect($query, $args);
        return array_shift(array_shift($result));
    }

    /**
     * Delete an entry form the database
     * @todo Improve the function to delete weak tables
     * @param  Object $object Object to be deleted
     * @return boolean Wheter succeed or not
     */
    public function delete($object) {
        $id_name = $this->id_name;
        return $this->deleteById($object->$id_name);
    }

    /**
     * Delete an entry by its id
     * @param  mixed $id id of the entry to be deleted
     * @return boolean whether succeds or not
     */
    public function deleteById($id) {
        global $database;
        $query  = 'DELETE FROM '.$this->table_name;
        $query .= ' WHERE '.$this->id_name.' = :id';
        $args[] = $this->getParamArray(':id', $id, $this->getColumns()[$this->id_name]['pdo']);
        return $database->singleExecute($query, $args);
    }

    /**
     * Return the objects with the characteristics provided to the helper, in
     * case of id provided then it will only return the object with the desired
     * id
     * @param  mixed $id
     * @return mixed
     */
    public function get($id = null) {
        /* if id privided then we set the where clause to retrieve only the
         * object with the desired id */
        if ($id) $this->setId($id);

        $results = $this->buildSQL();
        $objects = $this->build($results);

        /* if id provided then it only will return an object, so we check and in
         * true we return the first object, else we return the objects normally
         */
        return ($id) ? $objects[0] : $objects;
    }

    /**
     * Create and returns an array of all the entries in the database
     * @return array
     */
    public function getAll() {
        $results = $this->buildSQLAll();
        return $this->build($results);
    }

    /**
     * Makes sure that table_columns exists and return the array
     * @return array (See protected property $table_columns)
     */
    public function getColumns() {
        $this->retrieveColumns();
        return $this->table_columns;
    }

    /**
     * Get the keys of the table, get the name of every column in the table
     * $table_name
     * @return array[string]
     */
    public function getKeys() {
        if (!$this->table_keys) {
            $this->table_keys = array_keys($this->getColumns());
        }
        return $this->table_keys;
    }

    /**
     * Get the lower rango to start the pagination
     * @return integer initial page number
     */
    public function getLowerRange() {
        $lower = $this->page_number - 2;
        return ($lower > 0) ? $lower : 1;
    }

    /**
     * Get the next page number, if there are not next page it return nothing
     * @return integer If exists next page it will retrieve it
     */
    public function getNextPage() {
        $next = $this->page_number + 1;
        if ($next <= $this->getTotalPages()) {
            return $next;
        }
    }

    /**
     * Create and returns an array suitable to be include as $arg array in
     * PDOHandler queries
     * @param  string  $arg_name  Name of the key
     * @param  mixed   $arg_value Value to be bound
     * @param  integer $arg_type  PDO::PARAM_*
     * @return array
     */
    public function getParamArray($arg_name, $arg_value, $arg_type) {
        $array = array();
        foreach($this->prepare_keys as $key) {
            $arg = 'arg_'.$key;
            $array[$key] = $$arg;
        }
        return $array;
    }

    /**
     * get the previous page, if there are not previous page it will return
     * nothing
     * @return integer If found previous page return its number
     */
    public function getPreviousPage() {
        $prev = $this->page_number - 1;
        if ($prev > 0) return $prev;
    }

    /**
     * Build the query and save it in $query, also returns the value
     * @return string $query
     */
    public function getQuery() {
        $this->buildQuery();
        return $this->query;
    }

    /**
     * Return the total of pages
     * @return integer max number of pages
     */
    public function getTotalPages() {
        if (!isset($this->total_pages)) {
            $this->total_pages = ceil($this->count() / Database::$limit);
        }
        return $this->total_pages;
    }

    /**
     * Get the upper range of pages
     * @return integer Upper range
     */
    public function getUpperRange() {
        $upper = $this->page_number + 2;
        return ($upper <= $this->getTotalPages())
            ? $upper
            : $this->getTotalPages();
    }

    /**
     * Insert a new object to the database, it differs from save that if already
     * exists the function failed and return false
     * @param  Object $object Objecto to be saved
     * @return boolean Wheter succeed or nor
     */
    public function insert($object) {
        global $database;
        $query  = 'INSERT INTO '.$this->table_name.'(';

        foreach ($this->getColumns() as $key => $column_info) {
            if (isset($object->$key)) {
                $keys[] = $key;
                $values[] = ':'.$key;
                $args[] = $this->getParamArray(':'.$key, $object->$key, $column_info['pdo']);
            }
        }

        $query .= implode(', ', $keys).')';
        $query .= ' VALUES ('.implode(', ', $values).')';

        $result = $database->singleExecute($query, $args);
        if ($result && $this->id_name != null) {
            $id_name = $this->id_name;
            $id = $database->lastInsertId();
            $object->$id_name = $id;
        }

        return $result;
    }

    /**
     * Connects to the database and retrieves the columns for the table
     * specified in $table_name and save it in $table_columns
     */
    protected function retrieveColumns() {
        global $database;
        if (!$this->table_columns) {
            $this->table_columns = array();
            $temp_columns = $database->getTableColumns($this->table_name);
            while (($part = array_shift($temp_columns))) {
                $key = array_shift($part);

                /* add the pdo type */
                $part['pdo'] = (strpos($part['Type'], 'int') !== false)
                    ? PDO::PARAM_INT
                    : PDO::PARAM_STR;
                $this->table_columns[$key] = $part;
            }
        }
    }

    /**
     * insert the object in the database, in case it already exists or find a
     * single column diplicate it will update the values
     * @param  Object $object Object to be saved in the database
     * @return boolean Wheter it succeed or not
     */
    public function save($object) {
        global $database;
        $query = 'INSERT INTO '.$this->table_name.' (';
        $update_query = [];

        foreach ($this->getColumns() as $key => $column_info) {
            if (isset($object->$key)) {
                /* save the params */
                $keys[] = $key;
                $values[] = ':'.$key;
                $args[] = $this->getParamArray(':'.$key, $object->$key, $column_info['pdo']);

                /* if different to primary key added to update syntax */
                if (isset($this->id_name) && $key != $this->id_name) {
                    $update_query[] = "{$key} = VALUES ({$key})";
                }
            }
        }

        $query .= implode(', ', $keys);
        $query .= ') VALUES (';
        $query .= implode(', ', $values);
        $query .= ')';
        if ($update_query) {
            $query .= 'ON DUPLICATE KEY UPDATE ';
            $query .= implode(', ', $update_query);
        }
        return $database->singleExecute($query, $args);
    }

    /**
     * Will set a new where clause to search through the values
     * @todo Create a nee non destructive function to search
     * @param  string $value String to search through the columns
     * @return array[Object] array of the objects which match the search
     */
    public function search($value) {
        $query  = array();
        $args   = array();

        /* Create the search queries and values */
        foreach ($this->search_columns as $col) {
            $args[] = $this->getParamArray(':'.$col, '%'.$value.'%', PDO::PARAM_STR);
            $query[] = 'LOWER('.$col.') LIKE LOWER(:'.$col.')';
        }

        /* Create basic query */
        $query = implode(' OR ', $query);

        /* Add the and if already exists a where clause to avoid loose it */
        if (isset($this->where_query)) {
            $query = $this->where_query . ' AND (' . $query . ')';
            $args = array_merge($this->where_params, $args);
        }

        /* Set the new where clause */
        $this->setWhereClause($query, $args);
        return $this->get();
    }

    /**
     * Set the conditions to get the object with the id desired
     * @param mixed $id id to be bound in the syntaxis
     */
    public function setId($id) {
        $clause = $this->id_name.' = :id';
        $args[] = $this->getParamArray(':id', $id, PDO::PARAM_INT);
        $this->setWhereClause($clause, $args);
    }

    /**
     * Set the page to retrieve, necesary to calculate the next pages
     * @param integer $page_number Page number
     */
    public function setPage($page_number) {
        $this->limit = true;
        $this->page_number = $page_number;
        $this->offset = ($page_number - 1) * Database::$limit;
    }

    /**
     * Set the option to order the result by a column
     * @param string $column column name
     * @param const (ORDER_ASCEND | ORDER_DESCEND) $type   ascending or
     *        descending
     */
    public function setOrder($column, $type = self::ORDER_ASCEND) {
        $this->order_column = $column;
        $this->order_type = $type;
    }

    /**
     * Set the new $where_query, $where_args and set $build_where as true
     * @param string $query $where_query
     * @param array  $args  $where_args
     */
    public function setWhereClause($query, $args = []) {
        $this->build_where = true;
        $this->where_query = $query;
        $this->where_params = $args;
    }

    /**
     * Update the values from object into the database
     * @param  Object $object Objecto with the values to be updated
     * @return boolean result
     */
    public function update($object) {
        global $database;
        $query = 'UPDATE '.$this->table_name.' SET ';
        $id_name = $this->id_name;

        foreach ($this->getColumns() as $key => $column) {
            if (isset($object->$key) && $key != $this->id_name) {
                $update[] = $key.' = :'.$key;
                $args[] = $this->getParamArray(':'.$key, $object->$key, $column['pdo']);
            }
        }

        $query .= implode(', ', $update);
        $query .= ' WHERE '.$this->id_name.' = :'.$this->id_name;
        $args[] = $this->getParamArray(':'.$this->id_name, $object->$id_name, $this->getColumns()[$this->id_name]['pdo']);
        return $database->singleExecute($query, $args);
    }
}
