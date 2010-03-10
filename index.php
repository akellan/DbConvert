<?php 

set_time_limit(-1);

$mysql = new MySqlDb();
$sqlite = new SqLiteDb();

$mysql->CopyDataTo($sqlite);

echo $mysql->GetError();
echo $sqlite->GetError();

$mysql->Close();
$sqlite->Close();

////////////////// Start Class Implementations ///////////////////////

abstract class Db {
	var $db_res;
	
	abstract function Query($query);
	abstract function FetchObject($result);
	abstract function FetchArray($result);
	abstract function FetchAssocArray($result);
	abstract function FreeResult($result);
	abstract function GetError();
	abstract function GetTables();
	abstract function GetFields($table_name);
	abstract function EscapeString($string);
	abstract function Close();
			
	function GetResults($dbres, $use_fetch_method)
	{
		$results = array();
								
		$reflection = new ReflectionClass($this);
		$ref_method  = $reflection->getMethod($use_fetch_method);
		
		$temp_res;
		while ($temp_res = $ref_method->invoke($this, $dbres))
			$results[] = $temp_res;
			
		$this->FreeResult($dbres);
		
		return $results;
	}
	
	function GetArray($results)
	{
		return $this->GetResults($results , 'FetchArray');
	}

	function GetAssocArray($results)
	{
		return $this->GetResults($results , 'FetchAssocArray');
	}
	
	function GetObjects($results)
	{
		return $this->GetResults($results, 'FetchObject');
	}
	
	function CopyDataTo(Db $db_to)
	{
		function extractFieldName($field_data){
			return $field_data->Field;}
		
		$tables_from = $this->GetTables();
		$tables_to = $db_to->GetTables();
			
		$same_tables = array_intersect($tables_from, $tables_to);
		
		foreach ($same_tables as $table_name)
		{
			$fields_from = $this->GetFields($table_name);
			$fields_to = $db_to->GetFields($table_name);
			
			$fields_to_arr_name_type = array();
			
			foreach ($fields_to as $field_data)
				$fields_to_arr_name_type[$field_data->Field] = $field_data->Type;
			
			$same_fields = array_intersect(
				array_map('extractFieldName', $fields_from),
				array_map('extractFieldName', $fields_to));
			
			var_dump($fields_to_arr_name_type);
			
			$db_to->Query("DELETE FROM {$table_name};");
			
			var_dump("DELETE FROM {$table_name};");
			//continue;
			
			$db_to->Query('BEGIN TRANSACTION;');
				
			$fields = implode(',',$same_fields);
			
			var_dump($fields);
			
			$results = $this->Query("SELECT ".implode(',', $same_fields)." 
							FROM {$table_name}");
						
			$temp_data;
			while ($temp_data = $this->FetchAssocArray($results)) {
				
				$values;
				
				foreach ($same_fields as $field_name)
				{
					if(stristr($fields_to_arr_name_type[$field_name],'text') !== false || stristr($fields_to_arr_name_type[$field_name],'varchar') !== false)
						$values .= "'{$db_to->EscapeString($temp_data[$field_name])}',";
					else 
						$values .= "'{$temp_data[$field_name]}',";
				}
								
				$values = substr($values, 0, -1);
				
				$query = "INSERT INTO 
					{$table_name}({$fields})
			     		VALUES({$values})";
				
				$db_to->Query($query);
				
				$values = null;
			}	
			
			$this->FreeResult($results);			
			$db_to->Query('COMMIT;');
		}
	}
}

class SqLiteDb extends Db 
{
	function SqLiteDb()
	{
		$this->db_res = sqlite_open('auto.sdb');		
	}
	
	function GetTables()
	{
		$results = array();
		
		foreach ($this->GetArray($this->Query(
			"SELECT name 
			 FROM sqlite_master
			 WHERE type='table' ORDER BY name;" )) as $tbl_arr)
			array_push($results, $tbl_arr[0]);
		
		return $results;
	}
	
	function GetFields($table_name)
	{
		$c_types = sqlite_fetch_column_types($table_name,$this->db_res, SQLITE_ASSOC);
		
		$results = array();
		
		while (list($field,$type) = each($c_types)) {
		 	$new_t_obj = new stdClass();
		 	$new_t_obj->Field = $field;
		 	$new_t_obj->Type = strtolower($type);
		 	$results[] = $new_t_obj;
		 }
		 		
		return $results;
	}

	function FetchObject($result)
	{
		return sqlite_fetch_object($result);
	}
	
	function FetchArray($result)
	{
		return sqlite_fetch_array($result);
	}
	
	function FreeResult($result)
	{ }
	
	function Query($query)
	{
		return sqlite_query($query,$this->db_res);
	}
	
	function GetError()
	{
		return sqlite_error_string(sqlite_last_error($this->db_res));		
	}
	
	function EscapeString($string)
	{
		return sqlite_escape_string($string);
	}
	
	function FetchAssocArray($result)
	{
				
	}
	
	function Close()
	{
		sqlite_close($this->db_res);
	}
}

class MySqlDb extends Db
{
	var $db_name;
	
	function MySqlDb()
	{
		$this->db_name = 'seoempire';
		
		$this->db_res = mysql_connect('127.0.0.1','root','root');
		mysql_select_db($this->db_name, $this->db_res);
		mysql_set_charset('utf8', $this->db_res);
	}
	
	function GetTables()
	{
		$results = array();
		
		foreach ($this->GetArray($this->Query(
			"SHOW TABLES" )) as $tbl_arr)
			array_push($results, $tbl_arr[0]);
		
		return $results;
	}
	
	function GetFields($table_name)
	{
		return $this->GetObjects($this->Query(
			"SHOW COLUMNS FROM {$table_name}"));
	}

	function FetchObject($result)
	{
		return mysql_fetch_object($result);
	}
	
	function FreeResult($result)
	{
		mysql_free_result($result);
	}
	
	function Query($query)
	{
		return mysql_query($query, $this->db_res);
	}
	
	function GetError()
	{
		return mysql_error($this->db_res);
	}
	
	function FetchArray($res)
	{
		return mysql_fetch_array($res, $this->db_res);
	}

	function EscapeString($string)
	{
		return mysql_real_escape_string($string, $this->db_res);
	}
	
	function FetchAssocArray($res)
	{
		return mysql_fetch_assoc($res);
	}
	
	function Close()
	{
		mysql_close($this->db_res);
	}
}

/*$temp_time = strtotime($temp_obj->art_date);		
	
$temp_obj->art_day = date('d', $temp_time);
$temp_obj->art_month = date('m', $temp_time);
$temp_obj->art_year = date('Y', $temp_time);*/
		
?>
