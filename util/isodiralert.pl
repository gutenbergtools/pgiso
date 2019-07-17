#!/usr/bin/perl -Wall
use Mail::Sendmail;
# local $^W = 0;

$email = 'gbnewby@pglaf.org';

# get size of entire isos directory in kilobytes
$output = `/usr/bin/du -ksL /data/ftp/pgiso/isos`;
## print 'debug: du is $output\n';

chomp($output);

# grab size from output of du command
$output =~ m/^([0-9]+)\t/;
$size_kb = $1;

## print 'debug: size is $size_kb\n';

# send alert if size is over 100GB
# if($size_kb > 104857600)
# send alert if size is over 250GB
if($size_kb > 262144000)
{
	## print 'debug: starting loop\n';
  $size_gb = $size_kb / 1048576;
  $message = "[dante] /htdocs/pgiso/isos has exceeded 250GB.\n" .
             "Directory is currently at $size_gb GB.\n";

  %mail = (
    To => $email,
    Sender => 'gbnewby@dante.pglaf.org',
    Cc => '',
    Bcc => '',
    From => 'PG ISO Directory Monitor <pgiso@pglaf.org>',
    Subject => $message,
    Message => $message,
    Text => '',
    Body => '',
  );
  sendmail(%mail) || print "Error sending mail:
       $Mail::Sendmail::error\n";
}
## print 'debug: done, exiting\n';
exit 0;

