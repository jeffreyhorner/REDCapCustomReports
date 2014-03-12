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

$rid = '';
if (isset($_GET['report'])) { 
	$rid = $_GET['report'];
} 
$action = '';
if (isset($_GET['action'])){
	$action = $_GET['action'];
}
$devid = '';
if (isset($_GET['devid'])){
	$devid = $_GET['devid'];
}
$group = '';
if (isset($_POST['group'])){
	$group = $_POST['group'];
} elseif (isset($_GET['group'])){
	$group = $_GET['group'];
}

print '<div style="background-color: #D0D0D0; padding: 15px;">';
renderPageTitle("<img src='".APP_PATH_IMAGES."layout.png'> <i>PROJECT REPORTS DEVELOPER</i>");

$rdata_url="rdata.php?pid=$project_id";
	print '<p><a href="' . $rdata_url . '">Download RData for '.$project_name.'</a></p>';

//Get project_id for custom_report database
$CUSTOM_REPORT_ID = mysql_result(mysql_query("select project_id from redcap_projects where project_name = 'custom_report'"),0);

$q = mysql_query("select b.group_id,b.group_name from redcap_projects a, redcap_data_access_groups b where a.project_name='$project_name' and a.project_id=b.project_id");
$dags = array();
while ($row = mysql_fetch_array($q)){
	$dags[] = $row;
}

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
	$reports[] = $record;
}
print '<a target="_blank" href="'.APP_PATH_WEBROOT.'DataEntry/index.php?pnid=custom_report&page=report'.'">New Report</a>';
if (count($reports) > 0) {
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
		$data_entry=APP_PATH_WEBROOT."DataEntry/index.php?pnid=custom_report&page=report&id=".$report['id'];
		$hidden = ($report['hidden'] == 1)? 'hidden' : '';
		$active = ($report['active'] == 1)? 'active' : '';
		$hidact = "<i>($hidden $active)</i>";

		if ($rid==$report['id']){
			print "<tr style='background-color: $thisbg;'>
				<td style='padding: 3px 0 3px 0;color:#808080;font-size:11px;text-align:right;width:30px;'>$i.)&nbsp;</td>
				<td style='padding: 3px 0 3px 0;font-size:11px;'><b><a target=\"_blank\" href=\"$data_entry\" title=\"Edit database entry for $title\">$title</a></b></td>
				<td style='padding: 3px 0 3px 0;font-size:11px;'>$description $hidact</td>
				<td style='padding: 3px 0 3px 10px;text-align:right;'>
					<span style='color:#C0C0C0;'>";
			if ($report['report_type'] == 2){ # URL
				print "[<a style='color:#000060;font-size:11px;' href='$url'>view</a>]";
			} else {
				print "<form method=\"POST\" action=\"project_reports_developer.php?action=gen&pid=$project_id&report=".$report['id']."\">";
				print "<select name=\"group\">";
				foreach ($dags as $dag) {
					$selected =  ($dag['group_id'] == $group)? 'selected' : '';
					print "<option value=\"".$dag['group_id']."\" $selected>".$dag['group_name']."</option>";
				}
				print "</select>";
				print "<input type=\"submit\" value=\"Generate Report\"></form>";
			}
			print "</span></td></tr>\n";
			if ($action=='gen'){
				// Call rapache
				$reportVars = array();
				$reportVars['action'] = 'gen';
				$reportVars['report'] = $report['id'];
				$reportVars['group_id'] = $group;
				$reportVars['username'] = $user_rights['username'];
				$reportVars['super_user'] = $super_user;
				$reportVars['pnid'] = $project_name;
				$files = explode('|',rapache_service('ReportDevArea', $reportVars, NULL, FALSE));
				$newdevid = array_shift($files);
				print "<tr><td colspan=3>Files for group $group: ";
				foreach ($files as $file){
					print "<a target=\"_blank\" href=\"prdevfile.php?pid=$project_id&devid=$newdevid&file=$file\">$file</a> ";
				}
				print "</td></tr>";
			} elseif ($action=='clear'){
				// Call rapache
			}
		} else {
			print "<tr style='background-color: $thisbg;'>
				<td style='padding: 3px 0 3px 0;color:#808080;font-size:11px;text-align:right;width:30px;'>$i.)&nbsp;</td>
				<td style='padding: 3px 0 3px 0;font-size:11px;'><b><a target=\"_blank\" href=\"$data_entry\" title=\"Edit database entry for $title\">$title</a></b></td>
				<td style='padding: 3px 0 3px 0;font-size:11px;'>$description $hidact</td>
				<td style='padding: 3px 0 3px 10px;text-align:right;'>
					<span style='color:#C0C0C0;'>";
			if ($report['report_type'] == 2){ # URL
				print "[<a style='color:#000060;font-size:11px;' href='$url'>view</a>]";
			} else {
				print "<form method=\"POST\" action=\"project_reports_developer.php?action=gen&pid=$project_id&report=".$report['id']."\">";
				print "<select name=\"group\">";
				foreach ($dags as $dag) {
					print "<option value=\"".$dag['group_id']."\">".$dag['group_name']."</option>";
				}
				print "</select>";
				print "<input type=\"submit\" value=\"Generate Report\"></form>";
			}
			print "</span></td></tr>\n";
		}

		$i++;
	}

	print "</table></div>";
} else {
	print "<p>No Project Reports for this database.<p>";
}

	
print '</div>';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
