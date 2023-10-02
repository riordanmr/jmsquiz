# mkcreatetable.awk - Script to generate SQL to create a long table
# for the jmsquiz application.
# awk -f mkcreatetable.awk >createtable.sql
# MRR 2023-10-01
BEGIN {
    print "CREATE TABLE answers ("
    print "  jmsid varchar(32),"
    for(j=1; j<=30; j++) {
        sql = "  a" j " text NOT NULL default ''"
        if(j<30) sql = sql ","
        print sql
    }
    print ");"
}