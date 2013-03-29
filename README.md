REDCapCustomReports
===================

REDCapCustomReports is a REDCap module for creating custom PDF reports written in the R language. 

Requirements
============

Along with the requirements and dependencies for installing REDCap, the following must be installed on the REDCap server as well:

* R - http://www.r-project.org/
* rapache - http://www.rapache.net/

Installation
============

1. Copy the ProjectReports folder (located in the REDCap folder underneath the REDCapCustomReports distribution) into the redcap plugins folder. If a plugins folder does not exist, go ahead and create it. The redcap folder is the one from the REDCap project that you installed underneath a web root.

2. Edit the rapache_init_conn.php file and fill out the appropriate variables. They are:
- $RAPACHE_URL - this is typically either http://localhost, and be sure that your web server listens on the loopback device, e.g. 127.0.0.1.
- $RAPACHE_USER and $RAPACHE_PASS - These two are credentials that you may want to set up if you use HTTP BASIC authentication to project your rapache requests (described later).

3. Copy the file report-generator.R file somewhere outside of the DirectoryRoot of your web server but somewhere that the web server can read it. A good example is to create a directory under /var/rapache and place it in there.

4. Copy the contents of rapache/apache2.conf to the appropriate Apache configuration file for your site. Then edit to change the location of the report-generator.R file. If you followed the advice in step 3, then you shoudn't have to edit.

5. Create a REDCap project using the CustomReportDataDict.csv. The name of the project is irrelevant as you will only need the project id in the next step.

6. After creating the project above, you will want to discover the project id by mousing over the "Project Home" tab and look for the CGI variable name 'pid'. That's the part after index.php. For instance if the url looks like 'index.php?pid=55', then the project id is 55.

7. Under your REDCap install, you will find the database.php file. Edit that file and create the variable named 'CUSTOM_REPORT_ID' and assign the project id from step 6.

8. In the rapache file report-generator.R, you will need to edit two varaibles. They are:
- DBFILE - set this to the location of the REDCap database.php file
- EDOCPATH - set this to the location of your edoc root from REDCap.

9. Restart your server and you should be set.

Configuration
=============

Create Custom Application Link
------------------------------

The first step in configuring the plugin is creating a REDCap "Custom Application link". Do this by navigating to the "Control Center" and clicking on "Custom Application Links" under the "System Configuration" section. You will want add a link by filling out each field with the correct details:

1. Link Label - typically this has been "Project Reports Dev" where dev stands for developer, but you may enter anything you like here.

2. Link URL / Destination - very important to get this right. You will want to enter the full URL to the path of the "project_reports_developer.php" file located in the ProjectReports folder. For example if your REDCap software is installed on http://example.com/redcap, then  your Link URL will be 

  http://example.com/redcap/plugins/ProjectReports/project_reports_developer.php

3. Link Type - set this to "Simple Link"

4. User Access - choose the REDCap users who need access, those that will be developing reports.

5. Opens new window - leave unchecked

6. Append record info to URL - leave unchecked

7. Append project ID to URL - be sure to check this!


Create Project Link
-------------------

Now, you will want to follow these instructions to add a Project Bookmark  to your particular project. Note that you'll need to execute these steps for each project.

1. Click on the "Project Setup" tab on your project.

2. Under the "Setup project bookmark" section, click on "Add or edit bookmarks"

3. Add a new bookmark by entering the details for each field like so:

 - Link Label - typically "Project Records" but anything will do here.

 - Link URL / Destination - this will be the url of your REDCap site that points to the project_reports.php page. Following the example from above the URL should be:

   http://example.com/redcap/plugins/ProjectReports/project_reports.php

 - User Access - choose the users you would like to see the reports.

 - Opens new window - leave unchecked

 - Append record info to URL - leave unchecked

 - Append project ID to URL - be sure to check this!
