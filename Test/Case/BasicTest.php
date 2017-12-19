<?php

App::uses('Node', 'Neo4j.Model');

/**
 * Post Model for the test
 *
 * @package       app
 * @subpackage    app.model.post
 */
class Post extends Node {
    public $useDbConfig = 'test_neo4j';
}

class BasicTest extends CakeTestCase {
    public function setUp() {
        parent::setUp();
        $this->Post = ClassRegistry::init(array('class' => 'Post', 'alias' => 'Post'), true);

        $this->dropData();
    }

    public function tearDown() {
        $this->dropData();

        unset($this->Post);
        ClassRegistry::flush();
    }

    private function dropData() {
        $this->Post->deleteAll(true);
    }

        /**
     * Tests find method.
     *
     * @return void
     * @access public
     */
    public function testFind() {
        print(" <- " . __METHOD__ . "\r\n");
        $data = array(
            'title' => 'test1',
            'body' => 'aaaa',
            'text' => 'bbbb'
        );
        $this->Post->create();
        $this->Post->save($data);
        $result = $this->Post->find('all');
        $this->assertEqual(1, count($result));
        $resultData = $result[0]['Post'];
        $this->assertEqual(6, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $this->assertEqual($data['title'], $resultData['title']);
        $this->assertEqual($data['body'], $resultData['body']);
        $this->assertEqual($data['text'], $resultData['text']);
    }

    /**
     * Tests findBy* method
     *
     * @return void
     * @access public
     */
    public function testFindBy() {
        print(" <- " . __METHOD__ . "\r\n");
        $data = array(
            array(
                'title' => 'test',
                'body' => 'aaaa',
                'text' => 'bbbb'
            ),
            array(
                'title' => 'test2',
                'body' => 'abab',
                'text' => 'bcbc'
            ),
            array(
                'title' => 'test3',
                'body' => 'abab',
                'text' => 'cccc'
            )
        );

        foreach($data as $set) {
            $this->Post->create();
            $this->Post->save($set);
        }

        $result = $this->Post->findByTitle('test');
        $this->assertEqual(1, count($result));
        $resultData = $result['Post'];
        $this->assertEqual(6, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $this->assertEqual($resultData['title'], $data[0]['title']);
        $this->assertEqual($resultData['body'], $data[0]['body']);
        $this->assertEqual($resultData['text'], $data[0]['text']);

        $result = $this->Post->findByBody('abab');
        $this->assertEqual(1, count($result));
        $resultData = $result['Post'];
        $this->assertEqual(6, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $this->assertEqual($data[1]['title'], $resultData['title']);
        $this->assertEqual($data[1]['body'], $resultData['body']);
        $this->assertEqual($data[1]['text'], $resultData['text']);
    }

    /**
     * Tests findAllBy* method
     *
     * @return void
     * @access public
     */
    public function testFindAllBy() {
        print(" <- " . __METHOD__ . "\r\n");
        $data = array(
            array(
                'title' => 'test',
                'body' => 'abab',
                'text' => 'bbbb'
            ),
            array(
                'title' => 'test2',
                'body' => 'abab',
                'text' => 'bcbc'
            ),
        );

        foreach($data as $set) {
            $this->Post->create();
            $this->Post->save($set);
        }

        $result = $this->Post->findAllByBody('abab');
        $this->assertEqual(2, count($result));

        $result = $this->Post->findAllByTitle('test2');
        $this->assertEqual(1, count($result));
    }

    /**
     * Tests all kinds of conditions
     *
     * @return void
     * @access public
     */
    public function testConditions() {
        print(" <- " . __METHOD__ . "\r\n");
        $data = array(
            array(
                'title' => 'test',
                'body' => 'aaaa',
                'text' => 'bbbb',
                'count' => 37
            ),
            array(
                'title' => 'test2',
                'body' => 'abab',
                'text' => 'bcbc',
                'count' => 40
            ),
            array(
                'title' => 'test3',
                'body' => 'abab',
                'text' => 'cccc',
                'count' => 38
            ),
            array(
                'title' => 'test4',
                'body' => 'abab',
                'text' => 'dddd',
                'count' => 36
            )
        );
        foreach($data as $set) {
            $this->Post->create();
            $this->Post->save($set);
        }

        $results = $this->Post->find("all", array(
            "conditions" => array(
                "body" => "abab",
                "OR" => array(
                    "title" => "test2",
                    "text" => "dddd"
                )
            )
        ));
        $this->assertIdentical(2, count($results));
        $this->assertTrue(in_array($results[0]["Post"]["title"], array("test2", "test4")));
        $this->assertEqual($results[0]["Post"]["body"], "abab");
        $this->assertTrue(in_array($results[1]["Post"]["title"], array("test2", "test4")));
        $this->assertEqual($results[1]["Post"]["body"], "abab");

        $results = $this->Post->find("all", array(
            "conditions" => array(
                "body" => "abab",
                "not" => array(
                    "text" => "cccc"
                )
            )
        ));
        $this->assertIdentical(2, count($results));
        $this->assertTrue(in_array($results[0]["Post"]["title"], array("test2", "test4")));
        $this->assertEqual($results[0]["Post"]["body"], "abab");
        $this->assertTrue(in_array($results[1]["Post"]["title"], array("test2", "test4")));
        $this->assertEqual($results[1]["Post"]["body"], "abab");

        $results = $this->Post->find("all", array(
            "conditions" => array(
                "body" => "abab",
                "count >" => 36
            )
        ));
        $this->assertIdentical(2, count($results));
        $this->assertTrue(in_array($results[0]["Post"]["title"], array("test2", "test3")));
        $this->assertEqual($results[0]["Post"]["body"], "abab");
        $this->assertTrue(in_array($results[1]["Post"]["title"], array("test2", "test3")));
        $this->assertEqual($results[1]["Post"]["body"], "abab");
    }

    /**
     * Tests save method.
     *
     * @return void
     * @access public
     */
    public function testSave() {
        print(" <- " . __METHOD__ . "\r\n");
        $data = array(
            'title' => 'test',
            'body' => 'aaaa',
            'text' => 'bbbb'
        );
        $saveData['Post'] = $data;

        $this->Post->create();
        $saveResult = $this->Post->save($saveData, array("atomic" => false));
        $this->assertTrue(!empty($saveResult) && is_array($saveResult));

        $result = $this->Post->find('all');

        $this->assertEqual(1, count($result));
        $resultData = $result[0]['Post'];
        $this->assertEqual(6, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $this->assertEqual($this->Post->id, $resultData['_id']);
        $this->assertEqual($data['title'], $resultData['title']);
        $this->assertEqual($data['body'], $resultData['body']);
        $this->assertEqual($data['text'], $resultData['text']);

        $this->assertTrue(!empty($resultData['created']));
        $this->assertTrue(!empty($resultData['modified']));
    }

    /**
     * Tests insertId after saving
     *
     * @return void
     * @access public
     */
    public function testCheckInsertIdAfterSaving() {
        print(" <- " . __METHOD__ . "\r\n");
        $saveData['Post'] = array(
            'title' => 'test',
            'body' => 'aaaa',
            'text' => 'bbbb'
        );

        $this->Post->create();
        $saveResult = $this->Post->save($saveData, array("atomic" => false));
        $this->assertTrue(!empty($saveResult) && is_array($saveResult));


        $this->assertEqual($this->Post->id, $this->Post->getInsertId());
        $this->assertTrue(is_string($this->Post->id));
        $this->assertTrue(is_string($this->Post->getInsertId()));

        //set Numeric _id
        $saveData['Post'] = array(
            '_id' => 123456789,
            'title' => 'test',
            'body' => 'aaaa',
            'text' => 'bbbb'
        );

        $this->Post->create();
        $saveResult = $this->Post->save($saveData, array("atomic" => false));
        $this->assertTrue(!empty($saveResult) && is_array($saveResult));

        $this->assertEqual($saveData['Post']['_id'] ,$this->Post->id);
        $this->assertEqual($this->Post->id, $this->Post->getInsertId());
        $this->assertTrue(is_numeric($this->Post->id));
        $this->assertTrue(is_numeric($this->Post->getInsertId()));

        $readArray1 = $this->Post->read();
        $readArray2 = $this->Post->read(null, $saveData['Post']['_id']);
        $this->assertEqual($readArray1, $readArray2);
        $this->assertEqual($saveData['Post']['_id'], $readArray2['Post']['_id']);
    }

    /**
     * Tests saveAll method.
     *
     * @return void
     * @access public
     */
    public function testSaveAll() {
        print(" <- " . __METHOD__ . "\r\n");
        $saveData[0]['Post'] = array(
            'title' => 'test1',
            'body' => 'aaaa1',
            'text' => 'bbbb1'
        );

        $saveData[1]['Post'] = array(
            'title' => 'test2',
            'body' => 'aaaa2',
            'text' => 'bbbb2'
        );

        $this->Post->create();
        $saveResult = $this->Post->saveAll($saveData, array("atomic" => false));
        $result = $this->Post->find('all');
        $this->assertEqual(2, count($result));


        $resultData = $this->Post->find("first", array("conditions" => array("title" => "test1")));
        $resultData = $resultData["Post"];
        $this->assertEqual(6, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $data = $saveData[0]['Post'];
        $this->assertEqual($data['title'], $resultData['title']);
        $this->assertEqual($data['body'], $resultData['body']);
        $this->assertEqual($data['text'], $resultData['text']);

        $this->assertTrue(!empty($resultData['created']));
        $this->assertTrue(!empty($resultData['modified']));

        $resultData = $this->Post->find("first", array("conditions" => array("title" => "test2")));
        $resultData = $resultData["Post"];
        $this->assertEqual(6, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $data = $saveData[1]['Post'];
        $this->assertEqual($data['title'], $resultData['title']);
        $this->assertEqual($data['body'], $resultData['body']);
        $this->assertEqual($data['text'], $resultData['text']);

        $this->assertTrue(!empty($resultData['created']));
        $this->assertTrue(!empty($resultData['modified']));
    }

    /**
     * Tests update method.
     *
     * @return void
     * @access public
     */
    public function testUpdate() {
        print(" <- " . __METHOD__ . "\r\n");
        $count0 = $this->Post->find('count');

        $data = array(
            'title' => 'test',
            'body' => 'aaaa',
            'text' => 'bbbb',
            'count' => 0
        );
        $saveData['Post'] = $data;

        $this->Post->create();
        $saveResult = $this->Post->save($saveData, array("atomic" => false));
        $postId = $this->Post->id;

        $count1 = $this->Post->find('count');
        $this->assertIdentical($count1 - $count0, 1, 'Save failed to create one row');

        $this->assertTrue(!empty($saveResult) && is_array($saveResult));
        $this->assertTrue(!empty($postId) && is_string($postId));
        $findresult = $this->Post->find('all');
        $this->assertEqual(0, $findresult[0]['Post']['count']);

        $newData = array(
            '_id' => $findresult[0]['Post']['_id'],
            'title' => 'test1',
            'body' => 'aaaa1',
            'text' => 'bbbb1',
            'count' => 1
        );
        $this->Post->save(array("Post" => $newData), array("atomic" => true));

        $count2 = $this->Post->find('count');
        $this->assertIdentical($count2 - $count0, 1);
        $findresult = $this->Post->find('all');
        CakeLog::debug("new data: " . json_encode($newData));
        CakeLog::debug("find result: " . json_encode($findresult));
        $this->assertEqual($newData["count"], $findresult[0]['Post']['count']);
        $this->assertEqual($newData["body"], $findresult[0]['Post']['body']);
        $this->assertEqual($newData["text"], $findresult[0]['Post']['text']);
        $this->assertEqual($newData["title"], $findresult[0]['Post']['title']);
        $this->assertEqual($this->Post->id, $findresult[0]['Post']['_id']);

    }


    /**
     * Tests updateAll method.
     *
     * @return void
     * @access public
     */
    public function testUpdateAll() {
        print(" <- " . __METHOD__ . "\r\n");
        $saveData[0]['Post'] = array(
            'title' => 'test',
            'name' => 'ichi',
            'body' => 'aaaa1',
            'text' => 'bbbb1'
        );

        $saveData[1]['Post'] = array(
            'title' => 'test',
            'name' => 'ichi',
            'body' => 'aaaa2',
            'text' => 'bbbb2'
        );

        $this->Post->create();
        $this->Post->saveAll($saveData, array("atomic" => false));

        $updateData = array('name' => 'ichikawa');
        $conditions = array('title' => 'test');
        $resultUpdateAll = $this->Post->updateAll($updateData, $conditions);
        $this->assertTrue($resultUpdateAll);

        $result = $this->Post->find('all');
        $this->assertEqual(2, count($result));

        $resultData = $this->Post->find("first", array("conditions" => array("body" => 'aaaa1')));
        $resultData = $resultData["Post"];
        $this->assertEqual(7, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $data = $saveData[0]['Post'];
        $this->assertEqual($data['title'], $resultData['title']);
        $this->assertEqual('ichikawa', $resultData['name']);
        $this->assertEqual($data['body'], $resultData['body']);
        $this->assertEqual($data['text'], $resultData['text']);
        $this->assertTrue(!empty($resultData['created']));
        $this->assertTrue(!empty($resultData['modified']));


        $resultData = $this->Post->find("first", array("conditions" => array("body" => 'aaaa2')));
        $resultData = $resultData["Post"];
        $this->assertEqual(7, count($resultData));
        $this->assertTrue(!empty($resultData['_id']));
        $data = $saveData[1]['Post'];
        $this->assertEqual($data['title'], $resultData['title']);
        $this->assertEqual('ichikawa', $resultData['name']);
        $this->assertEqual($data['body'], $resultData['body']);
        $this->assertEqual($data['text'], $resultData['text']);
        $this->assertTrue(!empty($resultData['created']));
        $this->assertTrue(!empty($resultData['modified']));
    }

    /**
     * testSort method
     *
     * @return void
     * @access public
     */
    public function testSort() {
        print(" <- " . __METHOD__ . "\r\n");
        $data = array(
            'title' => 'AAA',
            'body' => 'aaaa',
            'text' => 'aaaa'
        );
        $saveData['Post'] = $data;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));

        $data = array(
            'title' => 'CCC',
            'body' => 'cccc',
            'text' => 'cccc'
        );
        $saveData['Post'] = $data;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));

        $this->Post->create();
        $data = array(
            'title' => 'BBB',
            'body' => 'bbbb',
            'text' => 'bbbb'
        );
        $saveData['Post'] = $data;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));

        $expected = array('AAA', 'BBB', 'CCC');
        $result = $this->Post->find('all', array(
            'fields' => array('_id', 'title'),
            'order' => array('title' => 'ASC')
        ));
        $result = Hash::extract($result, '{n}.Post.title');
        $this->assertEqual($expected, $result);

        $result = $this->Post->find('all', array(
            'fields' => array('_id', 'title'),
            'order' => array('title' => 'DESC')
        ));
        $result = Hash::extract($result, '{n}.Post.title');
        $expected = array_reverse($expected);
        $this->assertEqual($expected, $result);
    }

    /**
     * testLimit method
     *
     * @return void
     * @access public
     */
    public function testLimit() {
        print(" <- " . __METHOD__ . "\r\n");
        $data1 = array(
            'title' => 'AAA',
            'body' => 'aaaa',
            'text' => 'aaaa'
        );
        $saveData['Post'] = $data1;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));

        $data2 = array(
            'title' => 'CCC',
            'body' => 'cccc',
            'text' => 'cccc'
        );
        $saveData['Post'] = $data2;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));

        $this->Post->create();
        $data3 = array(
            'title' => 'BBB',
            'body' => 'bbbb',
            'text' => 'bbbb'
        );
        $saveData['Post'] = $data3;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));

        $resultData = $this->Post->find('all', array(
            'order' => array('title' => 'ASC'),
            'limit' => 2
        ));
        $this->assertIdentical(2, count($resultData));
        $this->assertEqual($data1['title'], $resultData[0]['Post']['title']);
        $this->assertEqual($data1['body'], $resultData[0]['Post']['body']);
        $this->assertEqual($data1['text'], $resultData[0]['Post']['text']);
        $this->assertEqual($data3['title'], $resultData[1]['Post']['title']);
        $this->assertEqual($data3['body'], $resultData[1]['Post']['body']);
        $this->assertEqual($data3['text'], $resultData[1]['Post']['text']);
    }

    /**
     * testFields method
     *
     * @return void
     * @access public
     */
    public function testFields() {
        print(" <- " . __METHOD__ . "\r\n");
        $data1 = array(
            'title' => 'AAA',
            'body' => 'aaaa',
            'text' => 'aaaa'
        );
        $saveData['Post'] = $data1;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));

        $resultData = $this->Post->find('all', array(
            'fields' => array('title')
        ));
        $this->assertIdentical(1, count($resultData));
        $this->assertIdentical(1, count($resultData[0]["Post"]));
        $this->assertEqual($data1['title'], $resultData[0]['Post']['title']);
    }

    /**
     * test commit and rollback in a transaction
     *
     * @return void
     * @access public
     */
    public function testTransaction() {
        print(" <- " . __METHOD__ . "\r\n");
        $data1 = array(
            'title' => 'Test Title',
            'body' => 'Test Body',
            'text' => 'Test Text'
        );
        $saveData['Post'] = $data1;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));
        $resultData = $this->Post->find("all");
        $this->assertIdentical(1, count($resultData));
        $this->assertEqual($data1['title'], $resultData[0]['Post']['title']);
        $this->assertEqual($data1['body'], $resultData[0]['Post']['body']);
        $this->assertEqual($data1['text'], $resultData[0]['Post']['text']);

        $db = $this->Post->getDataSource();

        // Test transaction failure
        $db->begin();
        $data2 = array(
            'title' => 'Test Title 1',
            'body' => 'Test Body 1',
            'text' => 'Test Text 1'
        );
        $saveData['Post'] = $data2;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));
        $db->rollback();
        $resultData = $this->Post->find("all");
        $this->assertIdentical(1, count($resultData));
        $this->assertEqual($data1['title'], $resultData[0]['Post']['title']);
        $this->assertEqual($data1['body'], $resultData[0]['Post']['body']);
        $this->assertEqual($data1['text'], $resultData[0]['Post']['text']);

        // Test transaction success
        $db->begin();
        $data3 = array(
            'title' => 'Test Title 2',
            'body' => 'Test Body 2',
            'text' => 'Test Text 2'
        );
        $saveData['Post'] = $data3;
        $this->Post->create();
        $this->Post->save($saveData, array("atomic" => false));
        $db->commit();
        $resultData = $this->Post->find("all");
        $this->assertIdentical(2, count($resultData));
        $bodies = Hash::extract($resultData, "{n}.Post.body");
        $this->assertTrue(in_array($data1["body"], $bodies));
        $this->assertTrue(in_array($data3["body"], $bodies));
    }

    /**
     * Tests query
     *  Distinct, Group
     *
     * @return void
     * @access public
     */
    public function testQuery() {
        print(" <- " . __METHOD__ . "\r\n");

        for($i = 0 ; $i < 30 ; $i++) {
            $saveData[$i]['Post'] = array(
                    'title' => 'test'.$i,
                    'body' => 'aaaa'.$i,
                    'text' => 'bbbb'.$i,
                    'count' => $i,
                    );
        }

        $this->Post->create();
        $saveResult = $this->Post->saveAll($saveData);
        $query = "MATCH (n:Post) RETURN n ORDER BY n.count ASC";
        $result = $this->Post->query($query);
        $data = $result["data"];
        $this->assertIdentical(30, count($data));
        $this->assertEqual($saveData[4]["Post"]["title"], Hash::get($data, "4.0.data.title"));
        $this->assertEqual($saveData[4]["Post"]["body"], Hash::get($data, "4.0.data.body"));
        $this->assertEqual($saveData[11]["Post"]["title"], Hash::get($data, "11.0.data.title"));
        $this->assertEqual($saveData[11]["Post"]["body"], Hash::get($data, "11.0.data.body"));
        $this->assertEqual($saveData[20]["Post"]["title"], Hash::get($data, "20.0.data.title"));
        $this->assertEqual($saveData[20]["Post"]["body"], Hash::get($data, "20.0.data.body"));
    }
}