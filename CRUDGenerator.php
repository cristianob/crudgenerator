<?php
/**
 * CRUDGenerator automaticaly creates CRUD form and display page.
 * Copyright (C) 2015 Cristiano Costa
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
function construct_element($element, $atributes, $innerHTML = "") {
	$elem = array("<".$element);
	
	foreach($atributes as $key => $value) {
		if($value === true)
			$elem[] = $key;
		elseif($value !== false)
			$elem[] = $key."=\"".$value."\"";
	}
	
	return implode(" ", $elem) . ">" . $innerHTML . "</" . $element . ">";
}


class CRUDGenerator {
	private $cn;
	private $table;
	
	protected $fields;
	protected $id;
	protected $action;
	protected $method;
	protected $submit_value;
	
	public function __construct($cn, $id, $action="", $method="POST", $submit_value="Enviar") {
		$this->cn = $cn;
		
		$this->fields = array();
		$this->id = $id;
		$this->action = $action;
		$this->method = $method;
		$this->submit_value = $submit_value;
	}
	
	public function add_field($field) {
		$this->fields[] = $field;
	}
	
	public function form_html($edit = false) {
		$values = array();
		if($edit !== false)
			$values = $this->cn->query("SELECT * FROM {$this->id} WHERE id=$edit")->fetch_assoc();
		
		$items_html = array();
		foreach($this->fields as $f) {
			$items_html[] = $f->form_html($edit ? $values[$f->id] : false);
		}
		
		if($edit !== false)
			$items_html[] = construct_element("input", array("type" => "hidden", "name" => "edit_id", "value" => $edit));
		
		$items_html[] = construct_element("input", array("type" => "submit", "value" => $this->submit_value));
		
		$html = construct_element("form", 
					  array("id" => $this->id, 
				 		"action" => $this->action, 
						"method" => $this->method), 
					  implode("", $items_html));
		
		return $html;
	}
	
	public function do_insert($post) {
		if(isset($post["edit_id"]))
			return $this->do_update($post);
			
		$sql_fields = array();
		$sql_values = array();
		foreach($this->fields as $f) {
			$sql_fields[] = "`".$f->id."`";
			$sql_values[] = "\"".$f->insert_format($post[$f->id])."\"";
		}
		
		$sql = "INSERT INTO {$this->id} (". implode(",", $sql_fields) .") VALUES (". implode(",", $sql_values) .")";
		return $this->cn->query($sql);		 
	}
	
	public function do_update($post) {
		$sql_values = array();
		foreach($this->fields as $f) {
			$sql_values[] = "`".$f->id."`=\"".$f->insert_format($post[$f->id])."\"";
		}
		
		$sql = "UPDATE {$this->id} SET ". implode(", ", $sql_values) ." WHERE `id`={$post["edit_id"]}";
		return $this->cn->query($sql);
	}
	
	public function do_display($display_fields = array(), $options = array()) {
		if($display_fields == array())
			$display_fields = $this->fields;
			
		if(isset($options["edit_enabled"]) && $options["edit_enabled"])
			if(!isset($options["edit_label"]))
				$options["edit_label"] = "Editar";
		
		$sql = "SELECT id, ". implode(", ", $this->_generate_property_array($display_fields, "id")) ." FROM {$this->id}";
		if(isset($options["order"]))
			$sql .= " ORDER BY {$options["order"]}";
		
		$res = $this->cn->query($sql);
		
		$table_html = array();
		$table_html[] = "<div id=\"dt_{$this->id}_table\"></div>\n";
		$table_html[] = '<script src="CRUDGenerator/display.js"></script>'."\n";
		$table_html[] = '<script>'."\n";
		$table_html[] = "dt_{$this->id}_fields = [";
		$fields_html = array();
		foreach($this->_generate_property_array($display_fields, "id") as $df) {
			$fields_html[] = '"'.$df.'"';
		}
		if(isset($options["edit_enabled"]) && $options["edit_enabled"])
			$fields_html[] = '"crud_edit_field"';
		$table_html[] = implode(",",$fields_html);
		$table_html[] = "];\n";
		
		$table_html[] = "dt_{$this->id}_headers = {";
		$headers_html = array();
		foreach($display_fields as $field) 
			$headers_html[] = $field->id.':"'.utf8_encode($field->label).'"';
		if(isset($options["edit_enabled"]) && $options["edit_enabled"])
			$headers_html[] = "crud_edit_field:\"\"";
		$table_html[] = implode(",", $headers_html);
		$table_html[] = "};\n";
		
		$table_html[] = "dt_{$this->id}_data = [";
		
		$data_html = array();
		while($l = $res->fetch_assoc()) {
			$row_html = array();
			
			foreach($display_fields as $field) 
				$row_html[] = $field->id.':"'.utf8_encode($field->select_format($l[$field->id])).'"';
			
			if(isset($options["edit_enabled"]) && $options["edit_enabled"])
				$row_html[] = "crud_edit_field:'<a href=\"?e={$l["id"]}\">{$options["edit_label"]}</a>'";
				
			$data_html[] = "{".implode(",", $row_html)."}";
		}
		$table_html[] = implode(",", $data_html);
		$table_html[] = "];\n";
		$table_html[] = "(function() {CRUDGenerator.generateTable(dt_{$this->id}_fields, dt_{$this->id}_headers, dt_{$this->id}_data, \"dt_{$this->id}_table\");})();\n";
		$table_html[] = '</script>'."\n";
		
		return implode("", $table_html);
	}
	
	private function _generate_property_array($obj_arr, $prop) {
		$ret = array();
		
		foreach($obj_arr as $obj) {
			$ret[] = $obj->$prop;
		}
		
		return $ret;
	}
}

class CRUDGenerator_Field {
	public $id;
	public $label;

	function __construct($id, $label = "") {
		$this->id = $id;
		$this->label = $label;
	}
	
	function form_html($value = false){}
	
	function select_format($value){
		return $value;
	}
	
	function insert_format($value) {
		return $value;
	}
}

class CRUDGenerator_InputField extends CRUDGenerator_Field {
	protected $size;
	protected $maxlength;
	
	function __construct($id, $label = "", $size = 20, $maxlength = false) {
		parent::__construct($id, $label);
		$this->size = $size;
		$this->maxlength = $maxlength;
	}
	
	function form_html($value = false) {
		$prop = array("type" => "text", 
			      "name" => $this->id, 
			      "id" => $this->id, 
			      "size" => $this->size,
			      "maxlength" => $this->maxlength);
			      
		if($value !== false) {
			$prop["value"] = $value;
		}
		
		$html =  construct_element("label", array("for" => $this->id), utf8_encode($this->label));
		$html .= construct_element("input", $prop);
		return $html;
	}
}

class CRUDGenerator_DateField extends CRUDGenerator_Field {
	protected $size;
	protected $maxlength;
	
	function __construct($id, $label = "", $size = 10, $maxlength = 10) {
		parent::__construct($id, $label);
		$this->size = $size;
		$this->maxlength = $maxlength;
	}
	
	function form_html($value = false) {
		$prop = array("type" => "date", 
			      "name" => $this->id, 
			      "id" => $this->id, 
			      "size" => $this->size,
			      "maxlength" => $this->maxlength);
			      
		if($value !== false) {
			$prop["value"] = $value;
		}
		
		$html =  construct_element("label", array("for" => $this->id), utf8_encode($this->label));
		$html .= construct_element("input", $prop);
		
		return $html;
	}
	
	function select_format($value) {
		$value = explode("-", $value);
		return $value[2]."/".$value[1]."/".$value[0];
	}
	
	function insert_format($value) {
		if($value[2] == "/") {
			$value = explode("/", $value);
			return $value[2]."-".$value[1]."-".$value[0];
		}
		
		return $value;
	}
}

class CRUDGenerator_HourField extends CRUDGenerator_Field {
	protected $size;
	protected $maxlength;
	
	function __construct($id, $label = "", $size = 5, $maxlength = 5) {
		parent::__construct($id, $label);
		$this->size = $size;
		$this->maxlength = $maxlength;
	}
	
	function form_html($value = false) {
		$prop = array("type" => "time", 
			      "name" => $this->id, 
			      "id" => $this->id, 
			      "size" => $this->size,
			      "maxlength" => $this->maxlength);
			      
		if($value !== false) {
			$prop["value"] = $value;
		}
		
		$html =  construct_element("label", array("for" => $this->id), utf8_encode($this->label));
		$html .= construct_element("input", $prop);
		
		return $html;
	}
	
	function select_format($value) {
		$value = explode(":", $value);
		return $value[0].":".$value[1];	
	}
}

class CRUDGenerator_AutoSelectField extends CRUDGenerator_Field {
	protected $cn;
	protected $table;
	protected $name_fld;
	protected $value_fld;
	protected $where;
	
	function __construct($id, $cn, $table, $name_fld, $value_fld, $label = "", $where = "") {
		parent::__construct($id, $label);
		$this->cn = $cn;
		$this->table = $table;
		$this->name_fld = $name_fld;
		$this->value_fld = $value_fld;
		$this->where = $where;
	}
	
	function form_html($value = false) {
		$option_html = array();
		
		/* Criando SQL da busca */
		if(is_string($this->name_fld))
			$name_fld = $this->name_fld;
		else
			$name_fld = implode(", ", array_slice($this->name_fld, 1));
		$sql = "SELECT {$name_fld}, {$this->value_fld} FROM {$this->table}";
		if($this->where != "")
			$sql .= " WHERE " . $this->where;

		/* Gerando os options */	
		$res = $this->cn->query($sql);
		while($l = $res->fetch_assoc()) {
			if(is_string($this->name_fld)) {
				$name_value = $l[$this->name_fld];
			} else {
				$name_value = array();
				foreach(array_slice($this->name_fld, 1) as $field)
					$name_value[] = $l[$field];
				$name_value = implode($this->name_fld[0], $name_value);
			}
			$option_html[] = construct_element("option", array("value" => $l[$this->value_fld]), utf8_encode($name_value));
		}
		
		$prop = array("name" => $this->id, 
			      "id" => $this->id);
		
		$html =  construct_element("label", array("for" => $this->id), utf8_encode($this->label));
		$html .= construct_element("select", 
					   $prop,
					   implode("", $option_html));
		
		if($value !== false) {
			$html .= construct_element("script", array(), "document.getElementById('{$this->id}').value = '$value'");
		}
		
		return $html;
	}
	
	function select_format($value) {
		/* Criando SQL da busca */
		if(is_string($this->name_fld))
			$name_fld = $this->name_fld;
		else
			$name_fld = implode(", ", array_slice($this->name_fld, 1));
			
		$fld = $this->cn->query("SELECT {$name_fld} FROM {$this->table} WHERE {$this->value_fld}=\"$value\"")->fetch_assoc();
		
		if(is_string($this->name_fld)) {
			return $fld[$this->name_fld];
		} else {
			$name_value = array();
			foreach(array_slice($this->name_fld, 1) as $field)
				$name_value[] = $fld[$field];
			return implode($this->name_fld[0], $name_value);
		}
	}
}
?>
