<?php
// Copyright (c) 2013 Tennessee Initiative for Perinatal Quality Care
// (TIPQC) All rights reserved.
//
// Redistribution and use in source and binary forms are permitted provided
// that the above copyright notice and this paragraph are duplicated
// in all such forms and that any documentation, advertising materials,
// and other materials related to such distribution and use acknowledge
// that the software was developed by TIPQC.  The TIPQC name may not be
// used to endorse or promote products derived from this software without
// specific prior written permission.  THIS SOFTWARE IS PROVIDED ``AS
// IS'' AND WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT
// LIMITATION, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
// A PARTICULAR PURPOSE.

// Individual Plots
function rapache_field_to_csv($app_name,$form,$field,$totalrecs,$group_id="") {
	
	// Collect metadata info
	$res = mysql_query("select element_label, element_validation_type, element_type, element_enum from redcap_metadata 
						where project_id = " . PROJECT_ID . " and field_name = '$field'");
	if (!$res){ return NULL; }
	$meta = mysql_fetch_array($res, MYSQL_NUM);
	if (!$meta){ return NULL; }	
	// Parse element_enum and truncate if any labels are too long
	$select_array = explode("\\n", strip_tags(label_decode2($meta[3])));
	$new_meta = "";
	foreach ($select_array as $key=>$value) {
		if (strpos($value,",")) {
			$pos = strpos($value,",");
			$this_value = trim(substr($value,0,$pos));
			$this_text = trim(substr($value,$pos+1));
			$new_meta .= "$this_value, $this_text \\n ";
		} else {
			$value = trim($value);
			$new_meta .= "$value, $value \\n ";
		}
	}
	$meta[3] = substr($new_meta,0,-3);	
	// Limit records pulled only to those in user's Data Access Group
	if ($group_id == "") {
		$group_sql  = ""; 
	} else {
		$group_sql  = "and record in (" . pre_query2("select record from redcap_data where project_id = " . PROJECT_ID . " and field_name = '__GROUPID__' and value = '$group_id'") . ")"; 
	}
	// Query to pull all existing data for this form and place into $data array
	$sql = "select value from redcap_data where project_id = " . PROJECT_ID . " and field_name = '$field' $group_sql";
	$res = mysql_query($sql);
	if (!$res){ return NULL; }
	$data = array();
	while ($ret = mysql_fetch_array($res, MYSQL_NUM)){
		$data[] = $ret[0];
	}
	if (count($data) == 0){ return NULL; }
	// Send back data string
	return $field . "|" . implode('|', $meta) . "|$totalrecs|" . implode('|', $data) . "\n";
	
}


// Descriptive Stats 
function rapache_fields_to_csv($app_name,$form,$totalrecs,$group_id="") {

	//Collect metadata info
	$res = mysql_query("select field_name, element_label, element_validation_type, element_type, element_enum from redcap_metadata 
						where project_id = ".PROJECT_ID." and form_name = '$form' order by field_order");
	$fields = "";
	while ($row = mysql_fetch_array($res,MYSQL_NUM)) {
		//Store metadata info
		$meta[$row[0]] = implode('|',$row);
		//Get list of fields to use in next query for the data pull
		$fields .= "'".$row[0]."', ";
	}
	$fields = substr($fields,0,-2);
	//Limit records pulled only to those in user's Data Access Group
	if ($group_id == "") {
		$group_sql = ""; 
	} else {
		$group_sql  = "and record in (" . pre_query("select record from redcap_data where field_name = '__GROUPID__' and value = '$group_id' and project_id = ".PROJECT_ID).")"; 
	}
	//Query to pull all existing data for this form and place into $data array
	$res = mysql_query("select * from redcap_data where project_id = ".PROJECT_ID." and field_name in ($fields) and value != '' $group_sql");
	while ($row = mysql_fetch_assoc($res)) {
		//If answer is not numerical, set data value to "x" to prevent passing of identifiable information
		if (!is_numeric($row['value'])) $row['value'] = "x";
		//Put data in array
		$data[$row['field_name']][$row['record']] = $row['value'];
	}
	//Loop through $meta array and append all data to end
	foreach ($meta as $this_field => $this_value) {
		$this_data = implode("|",$data[$this_field]);
		if ($this_data != "") $this_data = "|$this_data";
		$metatable[$this_field] = $meta[$this_field] . "|$totalrecs" . "$this_data\n";		
	}
	//print str_replace("\n","<br>",implode('',$metatable));
	return implode('',$metatable);
	
}


// Initiate CURL to communicate with R/Apache server
function rapache_service($service,$opt=NULL,$post_data=NULL,$debug=FALSE,$content_type=FALSE){
	include 'rapache_init_conn.php';
	$rapache_server = $RAPACHE_URL;
	$html = '';
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt ($ch, CURLOPT_URL, "$rapache_server/$service");
	curl_setopt ($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt ($ch, CURLOPT_HEADER, 0);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt ($ch, CURLOPT_USERPWD, "${RAPACHE_USER}:${RAPACHE_PASS}");
	if (!is_null($opt) || !is_null($post_data)){
		curl_setopt ($ch,CURLOPT_POST,1);
		if (!is_null($opt)){
			foreach( $opt as $k => $v ) $post[ ] = sprintf( "%s=%s", $k, urlencode( $v ) );
			$post_data=implode('&',$post);
		} 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($post_data))); 
		curl_setopt ($ch,CURLOPT_POSTFIELDS,$post_data);
	}
	$html = curl_exec ($ch);
	if ($debug){
		$html = '<!-- curl: ' . curl_getinfo($ch,CURLINFO_EFFECTIVE_URL). ' '. curl_error($ch) . '-->' . $html;
	}
	if ($content_type){
		$html = array('html'=>$html,'content_type'=>curl_getinfo($ch,CURLINFO_CONTENT_TYPE));
	}
	curl_close ($ch); 
	return $html;
}

/**
 * Run single-field query and return comma delimited set of values (to be used inside other query for better performance than using subqueries)
 */
function pre_query2($sql) {
	if (trim($sql) == "" || $sql == null) return "''";
	$q = mysql_query($sql);
	$val = "";
	if (mysql_num_rows($q) > 0) {
		while ($row = mysql_fetch_array($q)) {
			$val .= "'" . $row[0] . "', ";
		}
		$val = substr($val, 0, -2);
	}
	return ($val == "") ? "''" : $val;
}

/**
 * Decode limited set of html special chars rather than using html_entity_decode
 */
function label_decode2($val) {
	// Static arrays used for character replacing in labels/notes 
	// (user str_replace instead of html_entity_decode because users may use HTML char codes in text for foreign characters)
	$orig_chars = array("&amp;","&#38;","&#34;","&quot;","&#39;","&#60;","&lt;","&#62;","&gt;");
	$repl_chars = array("&"    ,"&"    ,"\""   ,"\""    ,"'"    ,"<"    ,"<"   ,">"    ,">"   );
	$val = str_replace($orig_chars, $repl_chars, $val);
	// If < character is followed by a number or equals sign, which PHP will strip out using striptags, add space after < to prevent string truncation.
	if (strpos($val, "<") !== false) {
		if (strpos($val, "<=") !== false) {
			$val = str_replace("<=", "< =", $val);
		}
		$val = preg_replace("/(<)([0-9])/", "< $2", $val);
	}
	return $val;
}
