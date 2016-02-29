<?php
class DatabaseTable {
    public static $table_name;

    public static function instantiate($array) {
        $object = new static;
        $object->populate($array);
        return $object;
    }

    public function populate($array) {
        foreach ($array as $key => $value) {
            $this->$key = $value;
        }
    }
}
