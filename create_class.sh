#!/bin/bash
salida=false
while [[ $# > 1 ]]; do
    key="$1"

    case $key in
        --table_name)
            table_name="$2"
            shift
            ;;
        --class_name)
            class_name="$2"
            shift;;
        --id_name)
            id_name="$2"
            shift;;
        *)
            ;;
    esac
    shift
done

if [ -z "$table_name" ]; then echo 'Debe definir --table_name VAR'; salida=true; fi
if [ -z "$class_name" ]; then echo 'Debe definir --class_name VAR'; salida=true; fi
if [ -z "$id_name" ]; then echo 'Debe definir --id_name VAR'; salida=true; fi
if $salida ; then exit; fi

echo "require_once __DIR__.'/${table_name}/${class_name}Helper.php';" >> 'includes.php'

mkdir $table_name
cd $table_name
echo "<?php
require_once __DIR__.'/../DatabaseTable.php';

class $class_name extends DatabaseTable {
    public static \$table_name = '$table_name';
}" > "${class_name}.php"

echo "<?php
require_once __DIR__.'/../DatabaseHelper.php';
require_once __DIR__.'/${class_name}.php';

class ${class_name}Helper extends DatabaseHelper {
    protected \$class_name = '${class_name}';
    protected \$id_name = '${id_name}';
    protected \$table_name = '${table_name}';

    public function __construct() {
        parent::__construct(
            \$this->table_name,
            \$this->class_name,
            \$this->id_name
        );
    }
}" > "${class_name}Helper.php"
