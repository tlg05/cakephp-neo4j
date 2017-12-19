<?php

class SchemalessBehavior extends ModelBehavior {

    public $name = 'Schemaless';

    public $settings = array();

    protected $_defaultSettings = array(
    );

    public function setup(Model $Model, $config = array()) {
    }

    /**
     * beforeSave method
     *
     * Set the schema to allow saving whatever has been passed
     *
     * @param mixed $Model
     * @return void
     * @access public
     */
    public function beforeSave(Model $Model, $config = array()) {
        $Model->cacheSources = false;
        $Model->schema(true);
        return true;
    }
}