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
include 'rapache_functions.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

renderPageTitle("<img src='".APP_PATH_IMAGES."layout.png'> Project Reports");

//Get project_id for custom_report database
$CUSTOM_REPORT_ID = mysql_result(mysql_query("select project_id from redcap_projects where project_name = 'custom_report'"),0);

$q = mysql_query("select record,field_name,value from redcap_data where project_id=$CUSTOM_REPORT_ID and record in ("
		. pre_query("select record from redcap_data where project_id=$CUSTOM_REPORT_ID and field_name='project_name' and value='$project_name'") . ") order by record desc");

$qarray = array();
while ($row = mysql_fetch_array($q)){
	if (!array_key_exists($row['record'],$qarray)){
		$qarray[$row['record']] = array();
	}
	$qarray[$row['record']][$row['field_name']] = $row['value'];
}
foreach ($qarray as $record) {
	if ($record['hidden'] == 2 && !$super_user) next; # These are hidden
	$reports[] = $record;
}

if ($user_rights['reports'] <= 0){
	print "<p>You are not allowed to view Project Reports.<p>";
} else if (count($reports) > 0) {
	print "<div style='max-width:700px;'><table width=100% cellpadding=3 cellspacing=0 style='border:1px solid #D0D0D0;font-family:Verdana,Arial;'>";
#			<tr><td style='border:1px solid #AAAAAA;font-size:14px;font-weight:bold;padding:5px;text-align:left;background-color:#DDDDDD;' colspan='4'>
#				Project Reports
#			</td></tr>";	
	$i = 1;
	foreach ($reports as $report) {
		$thisbg = ($i%2) == 0 ? '#FFFFFF' : '#EEEEEE';
		$title = $report['title'];
		$description = $report['description'];
		$url = $report['url'];
		print "<tr style='background-color: $thisbg;'>
			<td style='padding: 3px 0 3px 0;color:#808080;font-size:11px;text-align:right;width:30px;'>$i.)&nbsp;</td>
			<td style='padding: 3px 0 3px 0;font-size:11px;'><b>$title</b></td>
			<td style='padding: 3px 0 3px 0;font-size:11px;'>$description</td>
			<td style='padding: 3px 0 3px 10px;text-align:right;'>
				<span style='color:#C0C0C0;'>";
		if ($report['active'] == 1){
			if ($report['report_type'] == 2){ # URL
				print "[<a style='color:#000060;font-size:11px;' href='$url'>view</a>]";
			} else {
				# Only show if we are really live
				#if ($project_name=='custom_report' || $super_user ){
					print "[<a style='color:#000060;font-size:11px;' href=\"live_report.php?pid=$project_id&report=".$report['id']."\">view</a>]";
				#} else {
				#	print "generated report: coming soon";
				#}
			}
		} else {
			print "inactive";
		}
		print "
				</span>
			</td>
			</tr>";
		$i++;
	}

	print "</table></div>";
} else {
	print "<p>No Project Reports for this database.<p>";
}
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

	
include APP_PATH_DOCROOT . 'bottom.php';
