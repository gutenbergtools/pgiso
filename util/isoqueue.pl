#!/usr/bin/perl

#######################################
# Coded by Craig Stephenson           #
# Arctic Region Supercomputing Center #
# University of Alaska Fairbanks      #
# August 2005                         #
#######################################

use DBI;

# MySQL connection info
my $mysql_host = "localhost";
my $mysql_sock = "";
my $mysql_database = "gutenberg";
my $mysql_username = "gutenberg";
my $mysql_password = "blackberry";

# connect to MySQL
my $dbh = DBI->connect("DBI:mysql:mysql_socket=$mysql_sock:$mysql_database:$mysql_host", $mysql_username, $mysql_password);

# Loop forever, checking periodically for new ISOs to make:
while(true) {

    my $sql = "SELECT * FROM isoqueue ORDER BY time ASC";
    my $sth = $dbh->prepare($sql);
    $sth->execute;
    my $row = $sth->fetchrow_hashref;

    if($row == undef) {
	sleep 60;
	next;
    }

    # This actually runs the command to mkiso (the command is in the SQL row)
    system("$row->{command}");
    system ("echo $row->{command} at `/bin/date`| /usr/bin/mailx -s pgiso-output gbnewby\@pglaf.org");

    # Log:
    open (LOUT, ">>", "/home/gbnewby/pgiso/isoqueue-log.txt") or die "Problem opening logfile $!";
    print LOUT "$row->{command}" . "\n";
    close (LOUT);

    # Assume success; delete the command:
    $sql = "DELETE FROM isoqueue WHERE time='" 
	. $row->{time} 
    . "' AND command='" 
	. $row->{command} . "'";
    $sth = $dbh->prepare($sql);
    $sth->execute;
}
