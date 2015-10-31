# myDevBkp

myDevBkp lets you clone databases and tables from a production MySql database server for development purposes while ignoring specified databases or tables or specified rows from some tables. myDevBkp can also be used as a full fledged backup solution for your mysql databases.

As a developer you might want to restore a production database snapshot every couple of days but do not want to restore all the rows from those huge tables but still wanting the tables , tables structure and some subset of the data in the tables from the production server.

This tool also be used for taking a full mysql database backup such that you can have a seperate backup sql file for each table or view and one additional backup file for all the stored procedures together. Having database backups this way will make it extremely easy to retreive past data in one ore more selected tables.

#### Requirements
myDebBkp used mysqldump utility for taking the backups.  Based on how you are using it you might also need to have the `mysql` cli client utility.


#### Todo
Option to dump only tables with out views and triggers and storedprocedures<BR>
Option to dump data into one file and Views, Triggers and StoredProcedures into seperate files
