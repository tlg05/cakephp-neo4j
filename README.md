# Neo4j datasource for CakePHP 2.x

## Requirements

- PHP5
- CakePHP >= 2.2.5

## Installation

* This datasource send neo4j request through restful API. So no php driver for Neo4j is required.
* This project is a standard CakePHP plugin and it can be installed just like other plugins. 

Place the repository under the Plugin folder 

    cd my/app/Plugin 
    git clone git://github.com/tlg05/cakephp-neo4j.git Neo4j


Load the plugin in bootstrap.php

    CakePlugin::load("Neo4j");


Provider database server information in database.php:

<?php

    class DATABASE_CONFIG {
        public $neo4j = array(
            'datasource' => 'Neo4j.Neo4jSource',
            'host' => 'localhost',
            'port' => 34618,
            'login' => 'neo4j',
            'password' => 'password'
        );

        public $test_neo4j = array(
            'datasource' => 'Neo4j.Neo4jSource',
            'host' => 'localhost',
            'port' => 33110,
            'login' => 'neo4j',
            'password' => 'password'
        );
    }


> <b>Note</b> 
>
> * Please make sure the model files use schemaless behavior.
> * There is model Node and Relationship to be extended. These 2 kinds of models are differentiated by the property $modelType

## How it works

<b>The test cases contain thorough examples of the usages. </b>

Nodes can be managed like normal CakePHP data:

    $data = array(
        'title' => 'test1',
        'body' => 'aaaa',
        'text' => 'bbbb'
    );
    $this->Post->create();
    $this->Post->save($data);
    $data = $this->Post->find('all');

Relationships are special. We need to provide start node, end node and the properties for a relationship. <b>The properties of the relationship needs to be placed under properties tag instead of the root level of the data</b>:

    $data = array(
        'start' => 'Post',
        'end' => 'Writer',
        'conditions' => array(
            'start.title' => 'The Old Man and the Sea',
            'end.name' => 'Hemingway'
        ),
        'properties' => array(
            'note' => ‘Hemingway writes The Old Man and the Sea'
        )
    );
    $this->Write->create();
    $this->Write->save($data3, array("atomic" => false));


<b> Data association is not supported yet. </b>


## Author

Ligeng Te <tlgnewlife@gmail.com>