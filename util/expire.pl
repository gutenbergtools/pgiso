#!/usr/local/bin/perl

#######################################
# Coded by Craig Stephenson           #
# Arctic Region Supercomputing Center #
# University of Alaska Fairbanks      #
# August 2005                         #
#######################################

use DBI;
use File::stat;

# MySQL connection info
my $mysql_host = "localhost";
my $mysql_sock = "";
my $mysql_database = "gutenberg";
my $mysql_username = "gutenberg";
my $mysql_password = "blackberry";

# connect to MySQL
my $dbh = DBI->connect("DBI:mysql:mysql_socket=$mysql_sock:$mysql_database:$mysql_host", $mysql_username, $mysql_password);

my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime(time);

# turn date into a timestamp and subtract 5 days
$current = (($year-70)*31557600) + (($yday-5)*86400);

my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime($current);

# construct expiration year/month/day for MySQL delete statement
$xyear = 1900 + $year;
$xmonth = $mon;
$xday = $mday;

# delete rows that are at least a month old
$sql = "DELETE FROM pgiso_temp WHERE time < '" . $xyear . "-" . $xmonth . "-" . $xday . "'";
$dbh->do($sql);

# gbn: that's all.  Truncating the pgiso tree is done by another
# cron job.

exit;

# call recursive file expire function which deletes files whose modification date is older than a month
rexpire("/htdocs/pgiso/isos");

# recursive file expire function
sub rexpire
{
  my $location = $_[0];
  my $xdate = $_[1];
  my @contents;
  my $counter = 0;

  if(-f $location)
  {
    $st = stat($location);
    $timestamp = $st->mtime;

# print "delete if $timestamp < $current\n";

    if($timestamp < $current)
    {
      unlink($location);
    }
  }
  else
  {
    opendir(DIR, $location);
    while($item = readdir(DIR))
    {
      push(@contents, $item);
    }
    closedir(DIR);

    foreach $item(@contents)
    {
      if($item ne "." && $item ne "..")
      {
        rexpire($location . "/" . $item);
        $counter++;
      }
    }

    if($counter == 0 && $location ne "/htdocs/pgiso/isos")
    {
print $location . "\n";
      rmdir $location;
    }
  }
}
