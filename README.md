# DDNS-Updater

This script let you dynamic updates of A records for domains maintained with [PDNS Manager](https://github.com/loewexy/pdnsmanager/).

It is used for dynamic updating via dyndns clients like e.g. routers.

## Usage
First install PowerDNS with SQL-Backend and PDNSManager, then upgrade the database using userkey.sql.

Afterwards put the files to a webserver directory, insert your pdns database data and run composer (installing monolog for logging stuff). You'll need a directory ../log writeable for the webserver for logs.

For new hostnames first add a hostname via PDNS Manager (e.g. test.ddns.example.org). Once done insert a password for dynamic updates via bin/pdnsinsert.php -- -h HOSTNAME -p PASSWORD.

Then you can dynamically update the records using
 https://<your-url>/pdnsupd.php?hostname=HOSTNAME&passwd=PASSWORD
