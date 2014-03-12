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

header("Expires: 0");
header("cache-control: no-store, no-cache, must-revalidate"); 
header("Pragma: no-cache");
header('Content-type: application/octet-stream');
header("Content-disposition: inline;filename=$app_name.RData");

print rapache_service('REDCapProjectRData', array('pid'=>$project_id), NULL, FALSE);
?>
