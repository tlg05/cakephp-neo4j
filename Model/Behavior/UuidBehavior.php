<?php

/**
 * Utils UUID Behavior
 * 
 * @author Ligeng Te <teligeng@163.com>
 */
class UuidBehavior extends ModelBehavior {

    /**
     * Settings to configure the behavior
     *
     * @var array
     */
    public $settings = array();

    /**
     * Default settings
     *
     * code         - The field to store the code in
     *
     * @var array
     */
    protected $_defaults = array(
    );

    /**
     * Initiate behaviour
     *
     * @param object $Model
     * @param array $settings
     */
    public function setup(Model $Model, $settings = array()) {
        $this->settings[$Model->alias] = array_merge($this->_defaults, $settings);
    }

    /**
     * beforeSave callback
     *
     * @param object $Model
     */
    public function beforeSave(Model $Model, $options = array()) {
        $settings = $this->settings[$Model->alias];
        // only add code for new record
        if (!$Model->id && !isset($Model->data[$Model->alias][$Model->primaryKey])) {
            $Model->data[$Model->alias][$Model->primaryKey] = CakeText::uuid();
        }
        return true;
    }
}
