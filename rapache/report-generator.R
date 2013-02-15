
# Copyright (c) 2013 Tennessee Initiative for Perinatal Quality Care
# (TIPQC) All rights reserved.
# 
# Redistribution and use in source and binary forms are permitted provided
# that the above copyright notice and this paragraph are duplicated
# in all such forms and that any documentation, advertising materials,
# and other materials related to such distribution and use acknowledge
# that the software was developed by TIPQC.  The TIPQC name may not be
# used to endorse or promote products derived from this software without
# specific prior written permission.  THIS SOFTWARE IS PROVIDED ``AS
# IS'' AND WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT
# LIMITATION, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
# A PARTICULAR PURPOSE.
# require(DBI,quietly=TRUE)

require(RMySQL,quietly=TRUE)
library(XML)
library(brew)

DBFILE <- 'database.php'
EDOCPATH <- '/var/www/redcap/edocs'

l <- readLines(DBFILE)
DB <- gsub("[ ;']",'',unlist(strsplit(l[grep('$db',l,fixed=TRUE)],'='))[2])
DBUSER <- gsub("[ ;']",'',unlist(strsplit(l[grep('$username',l,fixed=TRUE)],'='))[2])
DBPASS <- gsub("[ ;']",'',unlist(strsplit(l[grep('$password',l,fixed=TRUE)],'='))[2])
CUSTOM_REPORT_ID <- gsub("[ ;']",'',unlist(strsplit(l[grep('$CUSTOM_REPORT_ID',l,fixed=TRUE)],'='))[2])

DBDRV <- MySQL()
rm(l)

newDevArea <- function(devId=NULL){
	if (is.null(devId))
		devId <- paste(floor(unclass(Sys.time())),gsub('^0.','',runif(1)),sep='')
	devArea <- file.path(tempdir(),devId)
	if (!file.exists(devArea)) dir.create(devArea)

	list(devId=devId,devArea=devArea)
}

prepareVarDB <- function(m,d){
	#FH$log('startPrepareVarDB')
	
	# Continuous variables
	if ((!is.na(m$element_validation_type) && m$element_validation_type %in% c('float','int') ) || (m$element_type %in% c('calc')) ){
		d <- as.numeric(d)

	# Ordinal or Categorical variable with a label
	} else if (m$element_type %in% c('select','radio') ) {
		# parses the string "0, Birth \\n 1, Death \\n, 2 Unknown" into a character
		# vector for creating a factor
		# XXX: need to fix labels like 'AfricanAmerican'
		w <- gsub(' ','',unlist(strsplit(m$element_enum,'\\n',fixed=TRUE)))
		if (length(w)>0) {
			if (length(w) == length(grep(',',w))){
				#w <- unlist(strsplit(w,','))
				# This version tolerates commas within the label
				w <- unlist(lapply(strsplit(w,',',perl=TRUE),function(x)c(x[1],paste(x[2:length(x)],collapse=', '))))
				# Create factor
				d <- factor(as.numeric(d),levels=as.numeric(w[seq(1,length(w),2)]),
						labels=w[seq(2,length(w),2)])
			} else if (length(w) == length(grep('^[0-9]+$',w,perl=TRUE))) {
				# Create numeric
				d <- as.numeric(d)
			} 
		}
	}
	#FH$log('stopPrepareVarDB')

	d
}
REDCapProjectData <- function(con,pnid){
	#FH$log('startGrabMetaData')
	# Grab the metadata
	mdata <- dbGetQuery(con,paste("select field_name,field_order,element_label,element_validation_type,element_type,element_enum from redcap_metadata where project_id=",pnid,sep=''))
	#FH$log('stopGrabMetaData')
	
	# gRAB the record id of the database
	field_id <- mdata[mdata$field_order==1,'field_name']

	# Grab the data
	retrieve_data <- function(field_name){
		#FH$log('startGrabData')
		x <- unlist(dbGetQuery(con,paste("select (select b.value from redcap_data b where b.project_id=",pnid," and b.record=a.record and b.field_name='",field_name,"' limit 1) as value from redcap_data a where a.project_id=",pnid," and a.field_name='",field_id,"'",sep='')))
		names(x) <- NULL
		#FH$log('stopGrabData')
		x
	}

	data <- data.frame(lapply(mdata[mdata$element_type!='checkbox','field_name'],
	function(n) {
	    prepareVarDB(as.list(mdata[mdata$field_name==n,]),retrieve_data(n))
	}
	), stringsAsFactors=FALSE)

	names(data) <- mdata[mdata$element_type!='checkbox','field_name']

	elementEnumValues <- function(element_enum){
		w <- gsub(' ','',unlist(strsplit(element_enum,'\\n',fixed=TRUE)))
			if (length(w)>0) {
				if (length(w) == length(grep(',',w))){
					w <- unlist(lapply(strsplit(w,',',perl=TRUE),function(x)c(x[1],paste(x[2:length(x)],collapse=', '))))
					return(as.numeric(w[seq(1,length(w),2)]))
				}
			}
		NULL
	}

	if (any(mdata$element_type=='checkbox')){
		for (name in mdata[mdata$element_type=='checkbox','field_name']){
			x <- dbGetQuery(con,paste("select value,record from redcap_data where project_id=",pnid," and field_name='",name,"' order by value",sep=''))
				for (val in elementEnumValues(mdata[mdata$field_name==name,'element_enum'])){
					newName <- paste(name,'___',val,sep='')
					data[[newName]] = 0L;
					if (length(x[x$value==val,'record']))
						data[data$study_id %in% x[x$value==val,'record'],newName] <- 1
				}
		}
	}

	data
}

cleanUpDatabaseConnection = function(con){
	r <- dbListResults(con)
	if (length(r) > 0)
		dbClearResult(r[[1]])
	dbDisconnect(con)
}

REDCapProjectRData <- function(){
	setContentType('application/octet-stream')

	# Connect to database
	con <- dbConnect(DBDRV,user=DBUSER,password=DBPASS,dbname=DB)
	if (!is(con,'MySQLConnection')) return(OK)
	on.exit(cleanUpDatabaseConnection(con))

	# Grab pnid
	pid <- as.integer(POST['pid'])
	if (length(pid) < 1 || is.na(pid)) return(OK)

	data <- REDCapProjectData(con,pid)
	t <- tempfile(as.character(Sys.getpid()))
	save(data,file=t)
	sendBin(readBin(t,'raw',n=file.info(t)$size))
	unlink(t)
	OK
}

runReport <- function(dev){
	# Grab report id
	rid <- as.integer(POST$report)
	if (length(rid) < 1 || is.na(rid)) return(OK)

	# Grab group id of requestor
	USER_GROUP_ID <- as.integer(POST$group_id)
	if (length(USER_GROUP_ID) < 1 || is.na(USER_GROUP_ID))
		USER_GROUP_ID <- NULL

	# Grab username of requestor
	USER <- POST$user
	if (length(USER) < 1 || is.na(USER)) return(OK)

	# Grab super user info
	SUPER_USER <- POST$super_user
	if (is.null(SUPER_USER) || SUPER_USER=="0"){
		SUPER_USER <- FALSE
	} else {
		SUPER_USER <- TRUE
	}

	# Connect to database
	con <- dbConnect(DBDRV,user=DBUSER,password=DBPASS,dbname=DB)
	if (!is(con,'MySQLConnection')) return(OK)
	on.exit(cleanUpDatabaseConnection(con))

	# Grab custom report id
	cid <- CUSTOM_REPORT_ID

	# Get report record
	sql <- paste("select field_name,value from redcap_data where project_id=",cid," and record=",rid,sep='')
	report = dbGetQuery(con,sql)
	if (length(report) < 1) return(OK)

	# Make sure this is indeed a report that should be generated
	if (report[report$field_name=='report_type','value'] != '1') return(OK)

	edoc_file <- function(edoc_id){
		sql <- paste('select stored_name,doc_name from redcap_edocs_metadata where doc_id=',edoc_id,sep='')
		rec <- dbGetQuery(con,sql)
		if (length(rec) == 2){
			 f <- file.path(EDOCPATH,rec[[1]])
		}
		if (file.exists(f)) return(list(path=f,name=rec[[2]]))

		# Error returns empty string
		''
	}
	# Any edoc files to copy to dev area
	edoc_file_1_id <- report[report$field_name=='edoc_file_1','value']
	edoc_file_1_name <- NULL
	lapply(report[grep('edoc_file',report$field_name),'value'],function(edoc_id){
		docF <- edoc_file(edoc_id)
		file.copy(docF$path,file.path(dev$devArea,docF$name))
		if (edoc_file_1_id==edoc_id){
			edoc_file_1_name <<- docF$name
		}
	})

	rc_db <- report[report$field_name=='project_name','value']
	if (nchar(rc_db) == 0) return(OK)
	sql <- paste("select project_id from redcap_projects where project_name='",rc_db,"'",sep='')
	rc_id <- dbGetQuery(con,sql)[[1]]

	rm(list=ls(envir=globalenv()),envir=globalenv())
	#FH$log('startREDCapProjectData')
	data <- REDCapProjectData(con,rc_id)
	#FH$log('stopREDCapProjectData')
	assign('data',data,envir=globalenv())
	assign('USER_GROUP_ID',USER_GROUP_ID,envir=globalenv())
	assign('USER',USER,envir=globalenv())
	assign('SUPER_USER',SUPER_USER,envir=globalenv())
	oldwd <- setwd(dev$devArea)
	sinkCon <- file('sink.txt')
	sink(sinkCon,type='output')
	sink(sinkCon,type='message')
	#FH$log('startRunReport')
	tryCatch(source(edoc_file_1_name),error=function(e)cat(e$message))
	#FH$log('stopRunReport')
	sink(type="message")
	sink()
	rm(list=ls(envir=globalenv()),envir=globalenv())
	#data <- REDCapProjectData(con,rc_id)
	close(sinkCon)
	setwd(oldwd)
}


mime_type <- function(path) {
  ext <- strsplit(path, ".", fixed = TRUE)[[1L]]
  n <- length(ext)
  
  if (n == 0) return('text/plain')
  
  types <- c(
    "css" = "text/css",
    "gif" = "image/gif",
    "js" = "text/javascript",
    "jpg" = "image/jpeg",
    "png" = "image/png",
    "html" = "text/html",
    "ico" = "image/x-icon",
    "pdf" = "application/pdf",
    "eps" = "application/postscript",
    "ps" = "application/postscript",
    "sgml"= "text/sgml",
    "xml" = "text/xml",
    "txt" = "text/plain"
  )
  
  type <- unname(types[ext[n]])

  ifelse(is.na(type),'text/plain',type)
}

transform_links <- function(report,args){
# Generate links like
# prdevfile.php?pnid=redcap_demo_d40def&devid=1286916127539748975308612&file=test.png
	fmt = paste('prdevfile.php?pnid=',args$pnid,'&devid=',args$devid,'&file=%s',sep='')
    text_xform <- function(x){ t <- x$value; class(t) <- 'AsIs'; xmlTextNode(t)}
	embelish_img <- function(t){
		xmlNode('img',attrs=c(src=sprintf(fmt,xmlAttrs(t)['src'])))
	}
	discard_cdata <- function(x,...){
		xmlTextNode(xmlValue(x),cdata=FALSE)
	}
	doc <- htmlTreeParse(report,asTree=TRUE,handlers=list(img=embelish_img,cdata=discard_cdata,text=text_xform))
	saveXML(doc$children$html,file=report,prefix='')
}


GenerateReport <- function(){
	#FH <<- startTiming(POST$report)
	dev <- newDevArea()
	runReport(dev)
	report <- file.path(dev$devArea,'report.pdf')

	# Backwards compatible: always search for pdf first
	if (file.exists(report)){
		setContentType('application/pdf')
	} else {
		# search for another "report.*" file.
		report <- dir(dev$devArea)[grep('^report.',dir(dev$devArea),perl=TRUE)[1]]
		report <- file.path(dev$devArea,report)
		if (!is.na(report)){
			type <- mime_type(report)
			setContentType(type)
			if (type == 'text/html'){
				transform_links(report,list(pnid=POST$pnid,devid=dev$devId))
			}
		} else {
			setContentType('text/plain')
		}
	}
	sendBin(readBin(report,'raw',n=file.info(report)$size))
	#FH$stopTiming()
	OK
}

ReportDevArea <- function(){
	#FH <<- startTiming(POST$devid)
	if (POST$action=='file'){
		dev <- newDevArea(POST$devid)
		if (POST$file %in% dir(dev$devArea)){
			devFile = file.path(dev$devArea,POST$file)
			#if (length(grep('.pdf',devFile)))
			#	setContentType('application/pdf')
			#else
			#	setContentType('text/plain')
			type <- mime_type(devFile)
			cat("sending",devFile,'[',type,']\n',file=stderr())
			setContentType(mime_type(devFile))
			sendBin(readBin(devFile,'raw',n=file.info(devFile)$size))
		} else {
			setContentType('text/plain')
			cat('File does not exist in devArea')
		}
	} else if (POST$action=='gen'){
		setContentType('text/plain')
		dev <- newDevArea()
		runReport(dev)
		# search for "report.*" file.
		report <- dir(dev$devArea)[grep('^report.',dir(dev$devArea),perl=TRUE)[1]]
		report <- file.path(dev$devArea,report)
		if (!is.na(report)){
			type <- mime_type(report)
			if (type == 'text/html'){
				transform_links(report,list(pnid=POST$pnid,devid=dev$devId))
			}
		}
		cat(dev$devId,paste(dir(dev$devArea),collapse='|'),sep='|')
	}
	#FH$stopTiming()
	OK
}
