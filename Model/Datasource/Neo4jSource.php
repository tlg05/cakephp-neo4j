<?php

App::uses('HttpSocket', 'Network/Http');
App::uses('SchemalessBehavior', 'Neo4j.Model/Behavior');

class Neo4jSource extends DataSource {

    public $_baseConfig = array(
        'host'       => 'localhost',
        'port'       => 7474,
        'login'     => '',
        'password'  => ''
    );

    protected $_defaultSchema = array(
        '_id' => array('type' => 'string', 'length' => 24, 'key' => 'primary'),
        'created' => array('type' => 'datetime'),
        'modified' => array('type' => 'datetime')
    );

    public $columns = array(
        'boolean' => array('name' => 'boolean'),
        'string' => array('name' => 'varchar'),
        'text' => array('name' => 'text'),
        'integer' => array('name' => 'integer', 'format' => null, 'formatter' => 'intval'),
        'float' => array('name' => 'float', 'format' => null, 'formatter' => 'floatval'),
        'datetime' => array('name' => 'datetime'),
        'timestamp' => array('name' => 'timestamp'),
        'time' => array('name' => 'time'),
        'date' => array('name' => 'date'),
    );

    private $transaction = array(
        "id" => null,
        "expires" => null
    );

    /**
     * Print full query debug info?
     *
     * @var bool
     */
    public $fullDebug = false;


    public function __construct($config) {
        parent::__construct($config);

        $this->fullDebug = Configure::read('debug') > 1;

        $this->Http = new HttpSocket();
    }

    public function isConnected() {
        try {
            $this->_requestData($data, array(
                'url' => $this->config['host'] . ":" . $this->config['port'] . '/db/data/',
                'raw_response' => true
            ));
        } catch (Exception $e) {
            CakeLog::error("Failed to connect the db. Message: " . $e->getMessage());
            return false;
        }
        return true;
    }

    public function describe($Model) {
        if(empty($Model->primaryKey)) {
            $Model->primaryKey = '_id';
        }

        $schema = array();
        
        if (!empty($Model->neo4jSchema) && is_array($Model->neo4jSchema)) {
            $schema = $Model->neo4jSchema;
            return $schema + array($Model->primaryKey => $this->_defaultSchema['_id']);
        } elseif (is_a($Model, 'Model') && !empty($Model->Behaviors)) {
            $Model->Behaviors->attach('Neo4j.Schemaless');
            /*
            if (!$Model->data) {
                if ($this->_db->selectCollection($table)->count()) {
                    return $this->deriveSchemaFromData($Model, $this->_db->selectCollection($table)->findOne());
                }
            }
            */
        }
        return $this->deriveSchemaFromData($Model);
    }

    private function deriveSchemaFromData($Model, $data = array()) {
        if (!$data) {
            $data = $Model->data;
            if ($data && array_key_exists($Model->alias, $data)) {
                $data = $data[$Model->alias];
            }
        }

        $return = $this->_defaultSchema;

        if ($data) {
            $fields = array_keys($data);
            foreach($fields as $field) {
                if (in_array($field, array('created', 'modified', 'updated'))) {
                    $return[$field] = array('type' => 'datetime', 'null' => true);
                } else {
                    $return[$field] = array('type' => 'string', 'length' => 2000);
                }
            }
        }

        return $return;
    }

    public function listSources($data = null) {
        return true;
    }

    public function calculate(Model $Model, $func, $params = array()) {
        return array('count' => true);
    }

    private function _saveData($Model, $query, $data) {
        if ($this->_transactionStarted && isset($this->transaction["expires"])) {
            $statements = array(
                "statements" => array(
                    array(
                        "statement" => $query,
                        "parameters" => $data
                    )
                )
            );
            $result = $this->_requestData($statements, array(
                'url' => $this->config['host'] . ":" . $this->config['port'] . '/db/data/transaction/' . $this->transaction["id"],
                'raw_response' => true
            ));
            if (empty($result["errors"])) {
                $id = Hash::get($result, "results.0.data.0.row.0._id");
                if ($id) {
                    $Model->setInsertID($id);
                    $Model->id = $id;
                    return $result;
                }
                $expires = Hash::get($result, "transaction.expires");
                if ($expires) {
                    $this->transaction["expires"] = strtotime($result["transaction"]["expires"]);
                }
            } else {
                $errors = $result["errors"];
                $errors = Hash::extract($errors, "{n}.message");
                $errorMessages = join(";", $errors);
                throw new InternalErrorException($errorMessages);
            }
            return $result;
        } else {
            $result = $this->execute($query, array(), $data);

            $id = Hash::get($result, "0.0.data._id");
            if ($id) {
                $Model->setInsertID($id);
                $Model->id = $id;
                return $result;
            }
        }
        return null;
    }

    private function _createNode(Model $Model, $fields = null, $data = null) {
        $pairs = array();
        foreach ($fields as $field) {
            $pairs[$field] = "{" . $field . "}";
        }
        $pairs = json_encode($pairs);
        $pairs = str_replace('"', "", $pairs);
        $query = "CREATE (n:" . $Model->alias . " " . $pairs . ") RETURN n";
        return $this->_saveData($Model, $query, $data) != null;
    }

    private function _createRelationship(Model $Model, $fields = null, $data = null) {
        if (empty($data["start"])) {
            throw new BadRequestException(__d("neo4j", "The alias of node 1 is required."));
        }

        if (empty($data["end"])) {
            throw new BadRequestException(__d("neo4j", "The alias of node 2 is required."));
        }

        if (empty($data["conditions"])) {
            throw new BadRequestException(__d("neo4j", "The conditions to match node 1 and node 2 are required."));
        }

        $node1 = $data["start"];
        $node2 = $data["end"];
        $conditions = $data["conditions"];

        $conditionsData = array();
        foreach ($conditions as $key => $val) {
            array_push($conditionsData, str_replace("end", "b", str_replace("start", "a", $key)) . "=" . json_encode($val));
        }
        $conditionsQuery = join(' AND ', $conditionsData);

        $propertiesData = array();
        if (!empty($data["properties"])) {
            foreach ($data["properties"] as $key => $val) {
                array_push($propertiesData, $key . ":" .  json_encode($val));
            }
        }
        $propertiesData[] = $Model->primaryKey . ":" . json_encode($data[$Model->primaryKey]);
        $propertiesQuery = join(",", $propertiesData);

        $query = "MATCH (a:" . $node1 . "),(b:" . $node2 . ") " .
                 "WHERE " . $conditionsQuery . " " .
                 "CREATE (a)-[r:" . $Model->alias . " {" . $propertiesQuery . "}]->(b) " .
                 "RETURN r";

        return $this->_saveData($Model, $query, $data) != null;
    }

    /**
     * Create Data
     *
     * @param Model $Model Model Instance
     * @param array $fields Field data
     * @param array $values Save data
     * @return boolean Insert result
     * @access public
     */
    public function create(Model $Model, $fields = null, $values = null) {
        if ($fields !== null && $values !== null) {
            $data = array_combine($fields, $values);
        } else {
            $data = $Model->data;
        }

        if ($Model->modelType == "node") {
            return $this->_createNode($Model, $fields, $data);
        } else if ($Model->modelType == "relationship") {
            return $this->_createRelationship($Model, $fields, $data);
        }
        return null;
    }

    private function _isComparisonOperator($key) {
        $strs = explode(" ", rtrim($key));
        if (count($strs) < 2) {
            return false;
        }
        $op = $strs[count($strs) - 1];
        return in_array($op, array("=", "<>", "<", ">", "<=", ">=", "IS"));
    }

    private function _getConditionQuery($conditions) {
        $conditionsData = array();
        foreach ($conditions as $key => $val) {
            if (!is_array($val)) {
                if ($this->_isComparisonOperator($key)) {
                    array_push($conditionsData, "n." . $key . json_encode($val));
                } else {
                    array_push($conditionsData, "n." . $key . "=" . json_encode($val));
                }
            } else {
                if (in_array(strtoupper($key), array("AND", "OR", "XOR"))) {
                    array_push($conditionsData, join(" " . $key . " ", $this->_getConditionQuery($val)));
                } else if (strtoupper($key) == "NOT") {
                    array_push($conditionsData, "NOT(" . join(" AND ", $this->_getConditionQuery($val)) . ")");
                }
            }
        }
        return $conditionsData;
    }

    /**
     * Read Data
     *
     * @param Model $Model Model Instance
     * @param array $query Query data
     * @param mixed  $recursive
     * @return array Results
     * @access public
     */
    public function read(Model $Model, $query = array(), $recursive = null) {
        $conditions = !empty($query["conditions"]) ? $query["conditions"] : array();
        $order = !empty($query["order"]) ? $query["order"] : array();
        $limit = !empty($query["limit"]) ? $query["limit"] : null;
        $fields = !empty($query["fields"]) ? $query["fields"] : null;
        $fields = !empty($fields) && !is_array($fields) ? array($fields) : $fields;

        if (!empty($order[0])) {
            $order = array_shift($order);
        }
        $this->_stripAlias($conditions, $Model->alias);
        $this->_stripAlias($fields, $Model->alias, false, 'value');
        $this->_stripAlias($order, $Model->alias, false, 'both');

        $returnQuery = "n";
        if ($Model->findQueryType === 'count') {
            $returnQuery = "count(n)";
        } else if (!empty($fields)) {
            $returnData = array();
            foreach ($fields as $field) {
                array_push($returnData, "n." . $field . " AS " . $field);
            }
            $returnQuery = join(",", $returnData);
        }

        if ($conditions === true) {
            $conditions = array();
        } elseif (!is_array($conditions)) {
            $conditions = array($conditions);
        }
        $conditionsData = $this->_getConditionQuery($conditions);
        $conditionsQuery = !empty($conditionsData) ? " WHERE " . join(" AND ", $conditionsData) : "";
        $order = (is_array($order)) ? $order : array($order);
        
        $orderData = array();
        foreach ($order as $key => $val) {
            if (is_numeric($key) || is_null($val)) {
                unset ($order[$key]);
                continue;
            }
            array_push($orderData, "n." . $key . " " . strtoupper($val));
        }
        $query = "";
        if ($Model->modelType == "relationship") {
            $query = "MATCH p=()-[n:" . $Model->alias  . "]->() " . $conditionsQuery . " RETURN p";
        } else {
            $query = "MATCH (n:" . $Model->alias . ")" . $conditionsQuery . " RETURN " . $returnQuery;
        }
        if (!empty($orderData)) {
            $orderQuery = join(",", $orderData);
            $query .= " ORDER BY " . $orderQuery;
        }
        if ($limit) {
            $query .= " LIMIT " . $limit;
        }
        $datas = $this->execute($query);

        $results = array();

        if ($Model->findQueryType !== 'count') {
            if ($Model->modelType == "relationship") {
                foreach ($datas as $i => $data) {
                    $relationshipUri = Hash::get($data, "0.relationships.0");
                    $startUri = Hash::get($data, "0.start");
                    $endUri = Hash::get($data, "0.end");
                    if (!$relationshipUri || !$startUri || !$endUri) {
                        continue;
                    } else {
                        $result = array();
                        $relationData = $this->execute(array(),
                            array("url" => $relationshipUri, "raw_response" => true, "method" => "get")
                        );
                        $result[$Model->alias] = $relationData["data"];

                        $startNodeData = $this->execute(array(),
                            array("url" => $startUri, "raw_response" => true, "method" => "get")
                        );
                        $result[$Model->alias]["start"] = array(
                            $startNodeData["metadata"]["labels"]["0"] => $startNodeData["data"]
                        );

                        $endNodeData = $this->execute(array(),
                            array("url" => $endUri, "raw_response" => true, "method" => "get")
                        );
                        $result[$Model->alias]["end"] = array(
                            $endNodeData["metadata"]["labels"]["0"] => $endNodeData["data"]
                        );
                        array_push($results, $result);
                    }
                }
            } else if (empty($fields)) {
                foreach ($datas as $data) {
                    if (count($data) > 0 && isset($data[0]["data"])) {
                        array_push($results, array($Model->alias => $data[0]["data"]));
                    }
                }
            } else {
                foreach ($datas as $i => $data) {
                    foreach ($data as $j => $val) {
                        $results[$i][$Model->alias][$fields[$j]] = $val;
                    }
                }
            }
        } else {
            $results = array(array($Model->alias => array('count' => $datas[0][0])));
        }
        return $results;
    }

    public function query() {
        $args = func_get_args();
        $query = $args[0];
        $params = array();
        if (count($args) > 1) {
            $params = $args[1];
        }

        if (count($args) > 1 && (strpos($args[0], 'findBy') === 0 || strpos($args[0], 'findAllBy') === 0)) {
            $params = $args[1];

            if (substr($args[0], 0, 6) === 'findBy') {
                $field = Inflector::underscore(substr($args[0], 6));
                return $args[2]->find('first', array('conditions' => array($field => $args[1][0])));
            } else {
                $field = Inflector::underscore(substr($args[0], 9));
                return $args[2]->find('all', array('conditions' => array($field => $args[1][0])));
            }
        }

        if (isset($args[2]) && is_a($args[2], 'Model')) {
            $this->_prepareLogQuery($args[2]);
        }
        $return = $this->_requestData(
            array("query" => $query),
            array("raw_response" => true));

        return $return;
    }

    public function update(Model $Model, $fields = null, $values = null, $conditions = null) {
        if ($fields !== null && $values !== null) {
            $data = array_combine($fields, $values);
        } elseif ($fields !== null && $conditions !== null) {
            return $this->updateAll($Model, $fields, $conditions);
        } else {
            $data = $Model->data;
        }

        if($Model->primaryKey !== '_id' && isset($data[$Model->primaryKey]) && !empty($data[$Model->primaryKey])) {
            $data['_id'] = $data[$Model->primaryKey];
            unset($data[$Model->primaryKey]);
        }

        if (empty($data['_id'])) {
            $data['_id'] = $Model->id;
        }

        $properties = array();
        if ($Model->modelType == "relationship") {
            $properties = !empty($data["properties"]) ? $data["properties"] : array();
        } else {
            $properties = $data;
        }
        $properties["_id"] = $data["_id"];

        $query = null;
        if (!empty($properties['_id'])) {
            $setClause = array();
            foreach ($properties as $key => $value) {
                array_push($setClause, "n." . $key . "=" . json_encode($value));
            }
            $query = 
                "MATCH (n:" . $Model->alias . " {_id:'" . $properties['_id'] . "'})" .
                " SET " . join(",", $setClause) .
                " RETURN n";
        } else {

        }
        $result = $this->_saveData($Model, $query, $properties);
        return $result;
    }

    public function delete(Model $Model, $conditions = null) {
        $this->_stripAlias($conditions, $Model->alias);

        $conditionClause = array();
        foreach ($conditions as $key => $val) {
            if (is_array($val)) {
                array_push($conditionClause, "n." . $key . " IN " . json_encode($val));
            } else {
                array_push($conditionClause, "n." . $key . ":" . json_encode($val));
            }
        }
        $conditionsQuery = join("AND", $conditionClause);

        if ($Model->modelType == "relationship") {
            $query = 
                "MATCH p=()-[n:" . $Model->alias . "]->() " .
                "WHERE " . $conditionsQuery . " " .
                "DELETE p";
        } else {
            $query = 
                "MATCH (n:" . $Model->alias . ") " .
                "WHERE " . $conditionsQuery . " " .
                "DELETE n";
        }

        $result = $this->execute($query);

        return true;
    }

    /**
     * Update multiple Record
     *
     * @param Model $Model Model Instance
     * @param array $fields Field data
     * @param array $conditions
     * @return boolean Update result
     * @access public
     */
    public function updateAll(&$Model, $fields = null,  $conditions = null) {
        $this->_stripAlias($conditions, $Model->alias);
        $this->_stripAlias($fields, $Model->alias, false, 'value');

        $setClause = array();
        foreach ($fields as $key => $value) {
            array_push($setClause, "n." . $key . "=" . json_encode($value));
        }

        $conditionData = array();
        foreach ($conditions as $key => $val) {
            array_push($conditionData, $key . ":" . json_encode($val));
        }
        $conditionClause = join(",", $conditionData);

        $query = 
            "MATCH (n:" . $Model->alias . " {" . $conditionClause . "})" .
            " SET " . join(",", $setClause) .
            " RETURN n";

        $result = $this->execute($query);

        return true;
    }

    private function getTable(Model $Model) {
        if (is_string($Model)) {
            $table = $Model->table;
        } else {
            $table = $Model->tablePrefix . $Model->table;
        }
        return $table;
    }

    public function execute($query, $options = array(), $params = null) {
        $data = array("query" => $query);
        if (isset($params) && is_array($params)) {
            $data["params"] = $params;
        }

        return $this->_requestData($data, $options);
    }

    private function _checkTransaction() {
        if ($this->_transactionStarted) {
            if (!isset($this->transaction["expires"]) || (time() > $this->transaction["expires"])) {
                $this->transaction = array(
                    "id" => null,
                    "expires" => null
                );
                $this->_transactionStarted = false;
                return false;
            }
        }
    }

    /**
     * Begin a transaction
     *
     * @return bool Returns true if a transaction is not in progress
     */
    public function begin() {
        $this->_checkTransaction();
        if ($this->_transactionStarted) {
            // Nested transaction is not supported yet
            return false;
        }
        $data = array(
            "statements" => array()
        );

        $result = $this->_requestData($data, array(
            'url' => $this->config['host'] . ":" . $this->config['port'] . '/db/data/transaction',
            'raw_response' => true
        ));

        if (empty($result["commit"]) || empty($result["transaction"])) {
            throw new InternalErrorException(isset($result["message"]) ? $result["message"] : "Failure");
        }
        $matches = array();
        preg_match('/transaction\/([0-9]+)\/commit/', $result["commit"], $matches);
        $this->transaction["id"] = $matches[1];
        if (!empty($result["transaction"]["expires"])) {
            $this->transaction["expires"] = strtotime($result["transaction"]["expires"]);
        } else {
            $this->transaction["expires"] = time() + 5 * 60;
        }
        $this->_transactionStarted = true;
        return true;
    }

    /**
     * Commit a transaction
     *
     * @return bool Returns true if a transaction is in progress
     */
    public function commit() {
        $this->_checkTransaction();
        if (!$this->_transactionStarted || !isset($this->transaction["id"])) {
            return false;
        }
        $data = array(
            "statements" => array()
        );
        $result = $this->_requestData($data, array(
            'url' => $this->config['host'] . ":" . $this->config['port'] . '/db/data/transaction/' . $this->transaction["id"] . '/commit',
            'raw_response' => true
        ));
        if (empty(!$result["errors"])) {
            return false;
        }
        $this->transaction = array(
            "id" => null,
            "expires" => null
        );
        $this->_transactionStarted = false;
        return true;
    }

    /**
     * Rollback a transaction
     *
     * @return bool Returns true if a transaction is in progress
     */
    public function rollback() {
        $this->_checkTransaction();
        if (!$this->_transactionStarted || !isset($this->transaction["id"])) {
            return false;
        }
        $data = array();
        $result = $this->_requestData($data, array(
            'url' => $this->config['host'] . ":" . $this->config['port'] . '/db/data/transaction/' . $this->transaction["id"],
            'raw_response' => true,
            'method' => 'delete'
        ));
        if (empty(!$result["errors"])) {
            return false;
        }
        $this->transaction = array(
            "id" => null,
            "expires" => null
        );
        $this->_transactionStarted = false;
        return true;
    }

    private function _requestData($data, $options = array()) {
        $this->Http->configAuth('Basic', $this->config['login'], $this->config['password']);
        $request = array(
            'header' => array(
                'Content-Type' => 'application/json',
                'X-Stream' => true
            )
        );
        $result = null;

        $defaultUrl = $this->config['host'] . ":" . $this->config['port'] . '/db/data/cypher';
        $requestUrl = isset($options["url"]) ? $options["url"] : $defaultUrl;
        $requestData = json_encode($data);
        $requestMethod = isset($options["method"]) ? $options["method"] : "post";
        $result = $this->Http->{$requestMethod}($requestUrl, $requestData, $request);
        $result = json_decode($result, true);
        if (isset($options["raw_response"]) && $options["raw_response"]) {

        } else if (!empty($result) && isset($result["data"])) {
            $result = $result["data"];
        } else {
            throw new InternalErrorException(isset($result["message"]) ? $result["message"] : "Failure");
        }
        return $result;
    }

    protected function _prepareLogQuery(&$Model) {
        return true;
    }

    /**
     * Convert automatically array('Model.field' => 'foo') to array('field' => 'foo')
     *
     * This introduces the limitation that you can't have a (nested) field with the same name as the model
     * But it's a small price to pay to be able to use other behaviors/functionality with mongoDB
     *
     * @param array $args array()
     * @param string $alias 'Model'
     * @param bool $recurse true
     * @param string $check 'key', 'value' or 'both'
     * @return void
     * @access protected
     */
    protected function _stripAlias(&$args = array(), $alias = 'Model', $recurse = true, $check = 'key') {
        if (!is_array($args)) {
            return;
        }
        $checkKey = ($check === 'key' || $check === 'both');
        $checkValue = ($check === 'value' || $check === 'both');

        foreach($args as $key => &$val) {
            if ($checkKey) {
                if (strpos($key, $alias . '.') === 0) {
                    unset($args[$key]);
                    $key = substr($key, strlen($alias) + 1);
                    $args[$key] = $val;
                }
            }
            if ($checkValue) {
                if (is_string($val) && strpos($val, $alias . '.') === 0) {
                    $val = substr($val, strlen($alias) + 1);
                }
            }
            if ($recurse && is_array($val)) {
                $this->_stripAlias($val, $alias, true, $check);
            }
        }
    }
}