# fakeMyBkp

fakeMyBkp lets you create copies of databases and tables from a production MySql database server for development purposes while ignoring specified databases or tables or specified rows from some tables. 

As a developer you might want to restore a production database snapshot every couple of days but do not want to restore all the rows from those huge tables but still wanting the tables , tables structure and some subset of the data in the tables from the production server.

This tool should be used for development purposes and must be never used as a full database backup.

