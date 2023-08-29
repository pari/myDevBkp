# myDevBkp

myDevBkp lets you clone databases and tables from a production MySql database server for development purposes while ignoring specified databases or tables or specified rows from some tables. myDevBkp can also be used as a full fledged backup solution for your mysql databases.

As a developer you might want to restore a production database snapshot every couple of days but do not want to restore all the rows from those huge tables but still wanting the tables , tables structure and some subset of the data in the tables from the production server.

This tool also be used for taking a full mysql database backup such that you can have a seperate backup sql file for each table or view and one additional backup file for all the stored procedures together. Having database backups this way will make it extremely easy to retreive past data in one ore more selected tables.

## Requirements
myDebBkp used mysqldump utility for taking the backups.  Based on how you are using it you might also need to have the `mysql` cli client utility.


## Usage 
```
php -q myDevBkp --generateconfig --h=HOST --u=USER --p=PASSWORD {--databases=db1,db2,db3} {--updateconfig=FILE}
	connects to mysql server HOST with USER and PASSWORD
	and generates initial config file outputs to stdout
	use output redirection to save into specific config file.
	You can later edit this file to add specific filter conditions
	against each table to backup only data of your interest.


php -q myDevBkp --useconfig=FILE --sh=HOST --su=USER --sp=PASSWORD --opf=dump.sql {--locktables='Y'}
	Parses config FILE and connects to source host 'sh'
	and dumps all backup data into a single dump sql output file
	while applying where conditions on each table - as configured in useconfing FILE



php -q myDevBkp --useconfig=FILE --sh=HOST --su=USER --sp=PASSWORD --opf=dump.sql --onlySPVIEWS='Y'
	Parses config FILE and connects to source host 'sh'
	and dumps only StoredProcedures and Views into a single opf dump sql file
	Will NOT use any where conditions on each table - as it does not loop through tables at all
	Just loops through databases mentioned in the config file



php -q myDevBkp --useconfig=FILE --sh=HOST --su=USER --sp=PASSWORD --fullbackup=BACKUP_PATH {--locktables='Y'}
	Parses config FILE and connects to source host 'sh'
	and dumps all backup data into a individual backups sql files under folder BACKUP_PATH
	while applying where conditions on each table - as configured in useconfing FILE



php -q myDevBkp --useconfig=FILE --sh=HOST --su=USER --sp=PASSWORD --dh=HOST --du=USER --dp=PASSWORD {--locktables='Y'}
	Parses config FILE and connects to source host 'sh'
	and dumps all backup data to another destination mysql-host dh
	while applying where conditions on each table - as configured in useconfing FILE
```

## Todo
Option to dump only tables with out views and triggers and storedprocedures<BR>
Option to dump data into one file and Views, Triggers and StoredProcedures into seperate files
