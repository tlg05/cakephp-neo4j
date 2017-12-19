<?php

App::uses('Node', 'Neo4j.Model');
App::uses('Relationship', 'Neo4j.Model');

/**
 * Post Model for the test
 *
 * @package       app
 * @subpackage    app.model.post
 */
class Post extends Node {
    public $useDbConfig = 'test_neo4j';
}

/**
 * Writer Model for the test
 *
 * @package       app
 * @subpackage    app.model.post
 */
class Writer extends Node {
    public $useDbConfig = 'test_neo4j';
}

/**
 * Writer Model for the test
 *
 * @package       app
 * @subpackage    app.model.post
 */
class Write extends Relationship {
    public $useDbConfig = 'test_neo4j';
}

class RelationshipTest extends CakeTestCase {
    public function setUp() {
        parent::setUp();
        $this->Post = ClassRegistry::init(array('class' => 'Post', 'alias' => 'Post'), true);
        $this->Writer = ClassRegistry::init(array('class' => 'Writer', 'alias' => 'Writer'), true);
        $this->Write = ClassRegistry::init(array('class' => 'Write', 'alias' => 'Write'), true);

        $this->dropData();
    }

    public function tearDown() {
        $this->dropData();

        unset($this->Post);
        unset($this->Writer);
        unset($this->Write);
        ClassRegistry::flush();
    }

    private function dropData() {
        $this->Write->deleteAll(true);
    }

    /**
     * Tests query
     *  Distinct, Group
     *
     * @return void
     * @access public
     */
    public function testCreateRelationships() {
        print(" <- " . __METHOD__ . "\r\n");
        $this->Write->deleteAll(true);

        $data1 = array(
            'title' => 'Test Title',
            'body' => 'Test Body',
            'text' => 'Test Text'
        );
        $saveData1['Post'] = $data1;
        $this->Post->create();
        $this->Post->save($saveData1, array("atomic" => false));


        $data2 = array(
            'name' => 'Test Name',
            'gender' => 'female',
            'age' => 36
        );
        $saveData2['Writer'] = $data2;
        $this->Writer->create();
        $this->Writer->save($saveData2, array("atomic" => false));
        
        $data3 = array(
            'start' => 'Post',
            'end' => 'Writer',
            'conditions' => array(
                'start.title' => $data1['title'],
                'end.name' => $data2['name']
            ),
            'properties' => array(
                'note' => $data2['name'] . ' writes ' . $data1['title']
            )
        );
        $this->Write->create();
        $this->Write->save($data3, array("atomic" => false));

        $relationshipCount = $this->Write->find("count");
        $this->assertIdentical(1, $relationshipCount);

        $relationshipData = $this->Write->find("all", array(
            "conditions" => array("note" => $data2['name'] . ' writes ' . $data1['title'])
        ));

        $this->assertIdentical(1, count($relationshipData));
        $this->assertEqual($relationshipData[0]["Write"]["note"], $data3["properties"]["note"]);
        $this->assertEqual($relationshipData[0]["Write"]["start"]["Post"]["title"], $data1["title"]);
        $this->assertEqual($relationshipData[0]["Write"]["end"]["Writer"]["name"], $data2["name"]);
    }

    public function testUpdateRelationships() {
        print(" <- " . __METHOD__ . "\r\n");

        $data1 = array(
            'title' => 'Test Title',
            'body' => 'Test Body',
            'text' => 'Test Text'
        );
        $saveData1['Post'] = $data1;
        $this->Post->create();
        $this->Post->save($saveData1, array("atomic" => false));


        $data2 = array(
            'name' => 'Test Name',
            'gender' => 'female',
            'age' => 36
        );
        $saveData2['Writer'] = $data2;
        $this->Writer->create();
        $this->Writer->save($saveData2, array("atomic" => false));
        
        $data3 = array(
            'start' => 'Post',
            'end' => 'Writer',
            'conditions' => array(
                'start.title' => $data1['title'],
                'end.name' => $data2['name']
            ),
            'properties' => array(
                'note' => $data2['name'] . ' writes ' . $data1['title']
            )
        );
        $this->Write->create();
        $this->Write->save($data3, array("atomic" => false));

        $relationshipData = $this->Write->find("all");
        $this->assertEqual($relationshipData[0]["Write"]["note"], 'Test Name writes Test Title');
        $this->assertTrue(!empty($relationshipData[0]["Write"]["_id"]));

        $updatedData = array(
            "Write" => array(
                "_id" => $relationshipData[0]["Write"]["_id"],
                "properties" => array(
                    "note" => "Updated notes"
                )
            )
        );
        $this->Write->save($updatedData, array("atomic" => false));

        $relationship = $this->Write->find("first", array(
            "conditions" => array("_id" => $updatedData["Write"]["_id"])
        ));
        $this->assertEqual($relationship["Write"]["note"], 'Test Name writes Test Title');
    }
}