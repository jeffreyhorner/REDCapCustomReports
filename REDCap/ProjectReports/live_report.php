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

// Call the REDCap Connect file in the main "redcap" directory
//define('REDCAP_WEBROOT','/');
require_once "../../redcap_connect.php";
error_reporting(E_ALL);
include 'rapache_functions.php';

# Need a report
if (!isset($_GET['report'])){
	header("Location: ".APP_PATH_WEBROOT."index.php?pid=$project_id");
	exit();
}

//Get project_id for custom_report database
$CUSTOM_REPORT_ID = mysql_result(mysql_query("select project_id from redcap_projects where project_name = 'custom_report'"),0);

// Get report record;
$sql = "select * from redcap_data where project_id=$CUSTOM_REPORT_ID and record=".$_GET['report'];
$q = mysql_query($sql);


$report = array();
while ($row = mysql_fetch_array($q)){
	$report[$row['field_name']] = $row['value'];
}

// Test if user is trying to view a report for which access has not been granted
if ($report['project_name'] != $project_name){
	log_event($sql,"redcap_data","OTHER",$_GET['report'],"report = ".$_GET['report'],"Access Denied for Reading Custom Project Report");
	header("Location: ".APP_PATH_WEBROOT."index.php?pid=$project_id");
	exit();
}
log_event($sql,"redcap_data","OTHER",$_GET['report'],"report = ".$_GET['report'],"Read Custom Project Report");

$reportVars = $_GET;
$reportVars['group_id'] = $user_rights['group_id'];
$reportVars['username'] = $user_rights['username'];
$reportVars['super_user'] = $super_user;
$html =  rapache_service('GenerateReport', $reportVars, NULL, FALSE,TRUE);
header('Content-type: ' . $html['content_type']);
if ($html['content_type'] == 'application/pdf'){
	header("Content-disposition: inline; filename=\"$project_name.pdf\"");
}
print $html['html'];
