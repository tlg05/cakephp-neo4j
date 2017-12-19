<?php

App::uses('Model', 'Model');

/**
 * Class Account
 */
class Relationship extends Model {

    public $useDbConfig = 'neo4j';

    public $primaryKey = '_id';

    public $actsAs = array('Neo4j.Schemaless', 'Neo4j.Uuid');

    public $modelType = "relationship";
}