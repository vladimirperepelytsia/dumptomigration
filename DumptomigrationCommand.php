<?php

/**
 * DumpToMigration class file.
 *
 * @author Perepelitsa Vladimir <vladimirperepelitsa101@gmail.com>
 * 
 * DumpToMigration command for creation clean dump from migrations Yii
 *
 */

class DumptomigrationCommand extends CConsoleCommand
{

    /**
     *
     * @var type 
     */
    public $migration_keys = [];
    public $migration_constraints = ['        $this->dbConnection->createCommand("SET FOREIGN_KEY_CHECKS=0")->execute();'];
    public $inserts_array = NULL;
    public $sorting_inserts = [];
    public $file_num = 1;
    public $file_size = 0;
    public $last_insert = '';

    /**
     * Получаем из файла конфигов запросы создания таблиц, заполнения таблиц, и создания внешних ключей
     * Get requests for creation tables, write data to tables and keys creation
     * $path - path to file with dump
     * $migration_table - table with migrations
     * $inserts_array - massive with table name to be filled
     */
    public function actionIndex()
    {
        ini_set('memory_limit', -1);
        $config = require_once (__DIR__.'/../config/migrate.php');
        $path = Yii::app()->getBasePath().'/..'.$config['path'];
        $migration_table = $config['migration_table'];
        $this->inserts_array = $config['inserts_array'];
        $file = fopen($path, "r"); //open file with base database
        $filesize = filesize($path); //get file size
        $chunk_size = 31457280; //chunk size
        $ceils = ceil($filesize/$chunk_size);
        $read = 0; //begin size
        $last_chunk = '';
        $tables = [];
        $this->create_insert_file();
        while($read < $ceils){ //print file chunks
            $sql = fread($file, $chunk_size);
            if(strpos($sql, "INSERT INTO") !== FALSE){
                $this->prepare_inserts($sql, '');
            }
            if(!empty($last_chunk)){
                $sql = $last_chunk.$sql;
            }
            if(strpos($sql, "CREATE TABLE") !== FALSE){ //get tables from chunk
                $prepare_tables = $this->prepare_tables($sql, $migration_table, TRUE); //prepare tables for writing
                $last_chunk = $prepare_tables['sql_last'];
                $tables = array_merge($tables, $prepare_tables['tables']);
            } 
            $read++;
        }
        $this->last_keys($last_chunk);
        $this->prepare_inserts($this->last_insert, 'last');
        $last_table = $this->prepare_tables($last_chunk, $migration_table, FALSE);
        $tables = array_merge($tables, $last_table['tables']);
        $this->cteate_migration_files($tables, '000001_tables');
        $this->migration_constraints[] = '        $this->dbConnection->createCommand("SET FOREIGN_KEY_CHECKS=0")->execute();';
        $this->cteate_migration_files(array_merge($this->migration_keys, $this->migration_constraints), '000003_keys');
        print_r("\n Migrations created! \n");
    }
    
    /**
     * Prepare tables to writing to migration file
     * @param type $sql
     * @param type $migration_table
     * @param type $last
     * @return string
     */
    public function prepare_tables($sql, $migration_table, $last){
        $tables = [];
        $sql_array = explode("CREATE TABLE",$sql);
        if($last === TRUE){
            $sql_last_str = array_pop($sql_array);
            $sql_last = strpos($sql_last_str, ';') !== FALSE ? substr($sql_last_str, 0, strpos($sql_last_str, ';')).";\n" : $sql_last_str;
        } else {
            $sql_last = '';
        }
        if(strpos($sql, "CREATE TABLE") !== 0){
            unset($sql_array[0]);
        }
        foreach ($sql_array as $create_key => $create){
            $rows = explode("\n", 'CREATE TABLE '.substr($create, 0, strpos($create, ';')).';');
            $table_name = $this->get_table_name($rows[0]);
            if($migration_table == $table_name){
                unset($sql_array[$create_key]);
            } else {
                if(!empty($table_name)){
                    unset($rows[0]);
                    $last_string = array_pop($rows);
                    $last_row = '        ), "'.  str_replace(';', '', substr($last_string, strpos($last_string, ')')+1))."\");\n\n";
                    $mig_table = '        $this->createTable("'.$table_name.'", array('."\n";
                    $tables[] = $mig_table.$this->prerare_migration_table_body($rows, $table_name)."\n".$last_row;
                }
            }
        }
        $result = ['tables' => $tables, 'sql_last' => 'CREATE TABLE '.$sql_last];
        return $result;
    }
    
    /**
     * Create migration file with tables
     * @param type $queries
     * @param type $name
     */
    public function cteate_migration_files($queries, $name){
        $create_tables_file = "m".date("ymd")."_".$name;
        $text = "<?php\n\nclass ".$create_tables_file." extends CDbMigration\n{\n    public function safeUp()\n    {\n".implode("", $queries)."\n}\n    public function safeDown()\n    {\n    }\n}";
        $crete_tables = fopen(Yii::app()->getBasePath()."/../common/migrations/".$create_tables_file.".php", "w");
        fwrite($crete_tables, $text);
        fclose($crete_tables);
    }
    
    /**
     * Get table name from sql query
     * @param type $row
     * @return type
     */
    public function get_table_name($row){
        $string = substr($row, strpos($row, '`')+1);
        $result = substr($string, 0, strpos($string, '`'));
        return $result;
    }
    
    /**
     * Prepare table body for writing to migration file
     * @param type $rows
     * @param type $table_name
     * @return type
     */
    public function prerare_migration_table_body($rows, $table_name){
        $table_body = [];
        foreach($rows as $row){
            if(strpos($row, 'PRIMARY KEY') !== FALSE){
                $row = substr($row, strpos($row, '(`')+2);
                $row_name = substr($row, 0, strpos($row, '`)'));
                foreach ($table_body as $key_tab => $tab){
                    $tab_str = substr($tab, 13);
                    $tab_name = substr($tab_str, 0, strpos($tab_str, '"'));
                    if($row_name == $tab_name){
                        $table_body[$key_tab] = str_replace('NOT NULL', 'PRIMARY KEY NOT NULL', $tab);
                    }
                }
            } else if(strpos($row, 'UNIQUE KEY') !== FALSE){
                $this->migration_keys[] = $this->parse_key($row, $table_name, 'TRUE');
            } else if(strpos($row, 'CONSTRAINT') !== FALSE){
                $this->migration_constraints[] = $this->prepare_constraints($row, $table_name); 
            } else if(strpos($row, 'KEY') !== FALSE){
                $this->migration_keys[] = $this->parse_key($row, $table_name, 'FALSE');
            } else {
                $string = substr($row, strpos($row, '`')+1);
                $name = substr($string, 0, strpos($string, '`'));
                $body_string = substr($string, strpos($string, '`')+1);
                if( strrpos($body_string, ',') !== FALSE){
                    $table_body[] = '            "'.$name.'" => "'.substr($body_string, 0, strrpos($body_string, ',')).'",';
                } else {
                    $table_body[] = '            "'.$name.'" => "'.substr($body_string, 0).'",';
                }
            }
        }
        $result = implode($table_body, "\n");
        return $result;
    }
    
    /**
     * Prepare keys to writing migration file
     * @param type $sql
     * @param type $table_name
     * @param type $unique
     * @return string
     */
    public function parse_key($sql, $table_name, $unique){
        if($unique == 'TRUE'){
            $sql_string = substr($sql, strpos($sql, 'UNIQUE KEY `')+12);
        } else {
            $sql_string = substr($sql, strpos($sql, 'KEY `')+5);
        }
        $name = substr($sql_string, 0, strpos($sql_string, '`'));
        $column_str = str_replace('`', '', substr($sql_string, strlen($name)+3));
        $column = '"'.substr($column_str, 0, strrpos($column_str, ')')).'"';
        $string = '        $this->createIndex("'.$name.'", "'.$table_name.'", '.$column.', '.$unique.');'."\n";
        return $string;
    }
    
    /**
     * Prepare constraints to writing migration file
     * @param type $sql
     * @param type $table_name
     * @return string
     */
    public function prepare_constraints($sql, $table_name){
        $sql_string = substr($sql, strpos($sql, 'CONSTRAINT `')+12);
        $name = substr($sql_string, 0, strpos($sql_string, '`'));
        $post_name_string = substr($sql_string, strpos($sql_string, '(`')+2);
        $columns = substr($post_name_string, 0, strpos($post_name_string, '`)'));
        $post_columns_string = substr($sql_string, strpos($sql_string, 'REFERENCES `')+12);
        $refTable = substr($post_columns_string, 0, strpos($post_columns_string, '`'));
        $post_refTable_string = substr($post_columns_string, strpos($post_columns_string, '(`')+2);
        $refColumns = substr($post_refTable_string, 0, strpos($post_refTable_string, '`)'));
        $delete_string = substr($sql, strpos($sql, 'ON DELETE ')+10);
        $delete = strpos($delete_string, ' ON UPDATE')!==FALSE ? substr($delete_string, 0, strpos($delete_string, ' ON UPDATE')) : 'RESTRICT';
        $update = strpos($sql_string, 'ON DELETE ')!==FALSE ? substr($sql_string, strpos($sql_string, 'ON UPDATE ')+10) : 'RESTRICT';
        if(strpos($update, ',') !== FALSE){
           $update =  substr($update, 0, strpos($update, ','));
        }
        $string = '        $this->addForeignKey("'.$name.'", "'.$table_name.'", "'.$columns.'", "'.$refTable.'", "'.$refColumns.'", "'.$delete.'", "'.$update.'");'."\n";
        return $string;
    }
    
    /**
     * Get last key and write it to array with other key
     * @param type $sql
     */
    public function last_keys($sql){
        $sql_array = explode('ALTER TABLE `', $sql);
        if(strpos($sql, "ALTER TABLE `") !== 0){
            unset($sql_array[0]);
        }
        foreach ($sql_array as $row){
            if(strpos($row, "CONSTRAINT `") !== FALSE){
                $row_array = explode('CONSTRAINT `', $row);
                $table_name = substr($row_array[0], 0, strpos($row_array[0], '`'));
                unset($row_array[0]);
                foreach ($row_array as $const) {
                    if(strpos($const, ';') !== FALSE){
                        $const_sql = substr($const, 0, strpos($const, ';'));
                    } else if (strpos($const, ',') !== FALSE){
                        $const_sql = substr($const, 0, strpos($const, ','));
                    }
                    $this->migration_constraints[] = $this->prepare_constraints('CONSTRAINT `'.$const_sql, $table_name);
                }
            }
        }
    }
    
    /**
     * Prepare date for writing to table
     * @param type $sql
     * @param type $condition
     */
    public function prepare_inserts($sql, $condition){
        $inserts = explode('INSERT INTO `', $this->last_insert.$sql);
        if(strpos($sql, "INSERT INTO `") !== 0){
            unset($inserts[0]);
        }
        if($condition != 'last'){
            $inserts_str = array_pop($inserts);
        } else {
            $inserts_str = '';
        }
        $inserts_last = strpos($inserts_str, ');') !== FALSE ? substr($inserts_str, 0, strpos($inserts_str, ');')).");" : $inserts_str;
        $this->last_insert = 'INSERT INTO `'.$inserts_last;
        foreach ($inserts as $i_key => $insert){
            $table_name = substr($insert, 0, strpos($insert, '`'));
            if (in_array($table_name, $this->inserts_array)){
                $create_tables_file = "m".date("ymd")."_000002_references_".$this->file_num;
                $crete_tables = fopen(Yii::app()->getBasePath()."/../common/migrations/".$create_tables_file.".php", "a");
                if(strlen($insert) != strrpos($insert, ");")+2){
                    $ins = substr($insert, 0, strrpos($insert, ");")+1);
                } else {
                    $ins = substr($insert, 0, -2);
                }
                $ins_str = str_replace(['\"', '"'], ['"', '\"'], $ins);
                $sorting_inserts = '        $this->dbConnection->createCommand("INSERT INTO `'.  str_replace("\n", "", $ins_str).'")->execute();'."\n";
                fwrite($crete_tables, $sorting_inserts);
                fclose($crete_tables);
                $this->file_size = $this->file_size+strlen($insert);
                if($this->file_size > 31457280){
                    $this->create_end_file();
                    $this->file_size = 0;
                    $this->create_insert_file();
                }
            }
        }
        if($condition == 'last'){
            $this->create_end_file();
        }
    }
    
    /**
     * Create migration file with table data
     */
    public function create_insert_file(){
        $create_tables_file = "m".date("ymd")."_000002_references_".$this->file_num;
        $crete_tables = fopen(Yii::app()->getBasePath()."/../common/migrations/".$create_tables_file.".php", "a");
        $begin_text = "<?php\n\nclass ".$create_tables_file." extends CDbMigration\n{\n    public function safeUp()\n    {\n        ini_set('memory_limit', -1);\n";
        fwrite($crete_tables, $begin_text);
        fclose($crete_tables);
    }
    
    /**
     * Close migration file
     */
    public function create_end_file(){
        $create_tables_file = "m".date("ymd")."_000002_references_".$this->file_num;
        $crete_tables = fopen(Yii::app()->getBasePath()."/../common/migrations/".$create_tables_file.".php", "a");
        fwrite($crete_tables, "\n}\n    public function safeDown()\n    {\n    }\n}");
        fclose($crete_tables);
        $this->file_num++;
    }
}