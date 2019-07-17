#!/usr/bin/perl -Wall

#######################################
# Coded by Craig Stephenson           #
# Arctic Region Supercomputing Center #
# University of Alaska Fairbanks      #
# August 2005                         #
#######################################

use DBI;
use Switch;
use Mail::Sendmail;
use File::Path;
use File::Basename;
use POSIX qw(strftime);

# MySQL connection info
my $mysql_host = "localhost";
my $mysql_sock = "";
my $mysql_database = "gutenberg";
my $mysql_username = "gutenberg";
my $mysql_password = "blackberry";

my $table=''; my $session=''; my $username=''; my $collection='';
my $format=''; my $limit=''; my $volid=''; my $isopath=''; my $email='';

# get command line arguments
for($i=0; $i < @ARGV; $i++)
{
    switch($ARGV[$i])
    {
	case "-t"	{ $table = $ARGV[$i+1] }
	case "-s"	{ $session = $ARGV[$i+1] }
	case "-u"	{ $username = $ARGV[$i+1] }
	case "-c"	{ $collection = $ARGV[$i+1] }
	case "-f"	{ $format = $ARGV[$i+1] }
	case "-l"	{ $limit = $ARGV[$i+1] }
	case "-v"	{ $volid = $ARGV[$i+1] }
	case "-p"	{ $isopath = $ARGV[$i+1] }
	case "-e"	{ $email = $ARGV[$i+1] }
    }
}

if (! defined($isopath)) {
    $isopath = "gutenberg.iso"; # default
}
if ($isopath !~ '.iso$') { 
    $isopath = $isopath . ".iso"; # friendly ending
}

# file system location variables

# gbn: Note that $sourcedir and $isotemp need to be on the same filesystem
$sourcedir = "/data/ftp/mirrors/gutenberg";
$isotemp = "/data/pgiso_temp";
$langdat = "/htdocs/pgiso/languages.dat";
my $domain = "pgiso.pglaf.org";
my $outwebroot = "/data/ftp/pgiso";

# initialize filename conversion associative array used to convert
# category names to filename-friendly names (i.e. all lower case)
foreach $letter('A'..'Z') {
    $fileconv{$letter} = lc $letter;
}

# non-alphabetic category to filename conversions
$fileconv{'#'} = "num";
$fileconv{'Unknown'} = "unk";
$fileconv{'Other'} = "oth";

# read language codes into an associative array
open(LANGDAT, $langdat);

while($line = <LANGDAT>) {
    @columns = split("\t", $line);
    chomp($columns[1]);
    $lang{$columns[0]} = $columns[1];
}
close(LANGDAT);

# clear temporary directory
system("rm -rf " . $isotemp . "/*");

# connect to MySQL database and set it for UTF-8 output
my $dbh = DBI->connect("DBI:mysql:mysql_socket=$mysql_sock:$mysql_database:$mysql_host", $mysql_username, $mysql_password);
$dbh->do("SET NAMES 'utf8'");

# re-create directory structure and create hard links to every file in
# user's collection
if( (defined($username)) && $collection ne "" && $table ne "" 
    && $format ne "") {
    $sql = "SELECT formats_sizes.files FROM " 
	. $table . " STRAIGHT_JOIN formats_sizes " .
	"WHERE " . $table . ".username='" . $username . "' AND " .
	$table . ".coll_id='" . $collection 
	. "' AND formats_sizes.id=" . $table . 
	".id AND formats_sizes.format=" . $table . ".format";

    $sth = $dbh->prepare($sql);
    $sth->execute;
} elsif( (defined($session)) && $table ne "" && $format ne "") {
    $sql = "SELECT formats_sizes.files FROM " 
	. $table . " STRAIGHT_JOIN formats_sizes " .
	"WHERE " . $table . ".session='" . $session . 
	"' AND formats_sizes.id=" . $table . 
	".id AND formats_sizes.format=" . $table . ".format";

    $sth = $dbh->prepare($sql);
    $sth->execute;
}

if (! $sth) {
    die "$0: Error: no query was executed.  Sorry.\n";
}

print "sql was: $sql\n";

my $bookcount=0; # debug

while (my $fileinfo = $sth->fetchrow_hashref) {
    $bookcount++;

#    my @files; # files to keep

    # First pass: look for duplicate text
    my @files = split(/;/, $fileinfo->{files});

    foreach my $file(@files) {
	# construct the full path of the source file
	my $sourcepath = $sourcedir . "/" . $file;

	# create an array with each directory in the path as a
	# separate element
	@filepath_array = split(/\//, $file);

	# pop the filename off of the filepath array
	pop(@filepath_array);

# These are just the files (with full paths), not the directories:
#	print "popping\t$sourcepath\n";

	# put the filepath array back together without filename for
	# use with HTML directories
#	$filepath = join("/", @filepath_array);

	# check to make sure this file exists before creating hard link
	if (-e $sourcepath) {
#	    print "calling rlink\t$sourcedir\t$isotemp\t$file";
	    rlink($sourcedir, $isotemp, $file);
	} else {
# debug:
	    print "Missing: $sourcepath\n";
	}
    }
}
print "debug: sql retrieved $bookcount items\n";

# create a 2D associative array of HTML code for records, sorted by author

if($username ne "" && $collection ne "" && $table ne "" && $format ne "") {
    $sql = "SELECT formats_sizes.* FROM " . $table 
	. " STRAIGHT_JOIN formats_sizes " 
	. "WHERE " . $table . ".username='" 
	. $username . "' AND " . $table . ".coll_id='" . $collection 
	. "' AND formats_sizes.id=" . $table . ".id "
	. "AND formats_sizes.format=" 
	. $table . ".format ORDER BY author,title ASC";

    $sth = $dbh->prepare($sql);
    $sth->execute;
}elsif($session ne "" && $table ne "" && $format ne "") {
    $sql = "SELECT formats_sizes.* FROM " . $table 
	. " STRAIGHT_JOIN formats_sizes "
	. "WHERE " . $table . ".session='" . $session . "' AND " 
	. "formats_sizes.id=" . $table . ".id AND formats_sizes.format=" 
	. $table . ".format " .	"ORDER BY author,title ASC";

    $sth = $dbh->prepare($sql);
    $sth->execute;
}

while(my $authorinfo = $sth->fetchrow_hashref) {

    # decode UTF-8 to combine with ASCII HTML, to be encoded back to
    # UTF-8 during file output
    utf8::decode($authorinfo->{title});
    utf8::decode($authorinfo->{author});

    if (! defined ($authorinfo->{id})) { $authorinfo->{id} = ""; }
    if (! defined ($authorinfo->{title})) { $authorinfo->{title} = ""; }
    if (! defined ($authorinfo->{author})) { $authorinfo->{author} = ""; }
    if (! defined ($authorinfo->{language})) { $authorinfo->{id} = "en"; }

    # create an HTML record of the current EText
    $record = "<font color=\"#b76b15\">EText-No.</font> " . $authorinfo->{id} . "<br />" .
	"<font color=\"#b76b15\">Title:</font> " . $authorinfo->{title} . "<br />" .
	"<font color=\"#b76b15\">Author:</font> " . $authorinfo->{author} . "<br />" .
	"<font color=\"#b76b15\">Language:</font> " . $lang{$authorinfo->{language}} . "<br />";

    # append links to each of this EText's files
    @files = split(/;/, $authorinfo->{files});

    # if first (and only) file is an HTML directory, create link for
    # each HTML file within
    if(substr($files[0], -2) eq "-h") {
	$basedir = $sourcedir . "/" . $files[0];
	opendir(DIR, $basedir);
	while($file = readdir(DIR)) {
	    if(substr($file, -3) eq "htm") {
		$file = $files[0] . "/" . $file;
		$record .= "<font color=\"#b76b15\">Link:</font> <a href=\"" . $file . "\">" . $file . "</a><br />";
	    }
	}
	closedir(DIR);
    }

    # if first file is not an HTML directory, create a link for each
    # file listed
    else {
	foreach $file(@files) {
	    $record .= "<font color=\"#b76b15\">Link:</font> <a href=\"" . $file . "\">" . $file . "</a><br />";
	}
    }

    # a newline character in an etext title denotes a subtitle,
    # replace newline with " - "
    $record =~ s/\n/ - /g;

    # append a newline character for future record-separation purposes
    $record .= "\n";

    # encode author metadata to UTF-8 for character comparisons
    utf8::encode($authorinfo->{author});

    # the @byauthor array has 29 keys: #, A-Z, Unknown, and Other each
    # key is treated as another array and the appropriate records are
    # pushed onto them
    switch(substr($authorinfo->{author}, 0, 1)) {
	case "" 	{ push(@{$byauthor{'Unknown'}}, $record) }
	case /[A-Za-z]/	{ push(@{$byauthor{uc substr($authorinfo->{author}, 0, 1)}}, $record) }
	case /[0-9]/	{ push(@{$byauthor{'#'}}, $record) }
	else		{ push(@{$byauthor{'Other'}}, $record) }
    }
}

# create a 2D associative array of HTML code for records, sorted by title
if($username ne "" && $collection ne "" && $table ne "" && $format ne "") {
    $sql = "SELECT formats_sizes.* FROM " . $table . " STRAIGHT_JOIN formats_sizes " .
	"WHERE " . $table . ".username='" . $username . "' AND " .
	$table . ".coll_id='" . $collection . "' AND formats_sizes.id=" . $table . ".id " .
	"AND formats_sizes.format=" . $table . ".format ORDER BY title,author ASC";
    $sth = $dbh->prepare($sql);
    $sth->execute;
}
elsif($session ne "" && $table ne "" && $format ne "") {
    $sql = "SELECT formats_sizes.* FROM " . $table . " STRAIGHT_JOIN formats_sizes " .
	"WHERE " . $table . ".session='" . $session . "' AND " .
	"formats_sizes.id=" . $table . ".id AND formats_sizes.format=" . $table . ".format " .
	"ORDER BY title,author ASC";
    $sth = $dbh->prepare($sql);
    $sth->execute;
}

while(my $titleinfo = $sth->fetchrow_hashref) {

    # decode UTF-8 to combine with ASCII HTML, to be encoded back to 
    # UTF-8 during file output
    utf8::decode($titleinfo->{title});
    utf8::decode($titleinfo->{author});

    if (! defined ($titleinfo->{id})) { $titleinfo->{id} = ""; }
    if (! defined ($titleinfo->{title})) { $titleinfo->{title} = ""; }
    if (! defined ($titleinfo->{author})) { $titleinfo->{author} = ""; }
    if (! defined ($titleinfo->{language})) { $titleinfo->{id} = "en"; }

    # create an HTML record of the current EText
    $record = "<font color=\"#b76b15\">EText-No.</font> " . $titleinfo->{id} . "<br />" .
	"<font color=\"#b76b15\">Title:</font> " . $titleinfo->{title} . "<br />" .
	"<font color=\"#b76b15\">Author:</font> " . $titleinfo->{author} . "<br />" .
	"<font color=\"#b76b15\">Language:</font> " . $lang{$titleinfo->{language}} . "<br />";

    # append links to each of this EText's files
    @files = split(/;/, $titleinfo->{files});

    # if first (and only) file is an HTML directory, create link for each 
    # HTML file within
    if(substr($files[0], -2) eq "-h") {
	$basedir = $sourcedir . "/" . $files[0];
	opendir(DIR, $basedir);
	while($file = readdir(DIR)) {
	    if(substr($file, -3) eq "htm") {
		$file = $files[0] . "/" . $file;
		$record .= "<font color=\"#b76b15\">Link:</font> <a href=\"" . $file . "\"> " . $file . "</a><br />";
	    }
	}
	closedir(DIR);
    }
    # if first file is not an HTML directory, create a link for each file listed
    else {
	foreach $file(@files) {
	    $record .= "<font color=\"#b76b15\">Link:</font> <a href=\"" . $file . "\">" . $file . "</a><br />";
	}
    }

    # a newline character in an etext title denotes a subtitle, replace 
    # newline with " - "
    $record =~ s/\n/ - /g;

    # append a newline character for future record-separation purposes
    $record .= "\n";

    # encode author metadata to UTF-8 for character comparisons
    utf8::encode($titleinfo->{title});

    # the @bytitle array has 29 keys: #, A-Z, Unknown, and Other
    # each key is treated as another array and the appropriate records are pushed onto them
    switch(substr($titleinfo->{title}, 0, 1)) {
	case "" 	{ push(@{$bytitle{'Unknown'}}, $record) }
	case /[A-Za-z]/	{ push(@{$bytitle{uc substr($titleinfo->{title}, 0, 1)}}, $record) }
	case /[0-9]/	{ push(@{$bytitle{'#'}}, $record) }
	else		{ push(@{$bytitle{'Other'}}, $record) }
    }
}

# define the order in which records and record links will be listed
@order = ("#", A..Z, "Unknown", "Other");

# initialize strings for author and title links
#$authorlinks = "Author: ";
#$titlelinks = "Title: ";

# step through each key in previously-defined order
my $authorlinks_alpha=''; my $titlelinks_alpha=''; my $dirlinks='';
foreach $key(@order) {

    # if this key has records in the @byauthor array, create HTML for
    # this list of records, the index-to-index links for single and
    # alpha indexes are also created here
    if(exists($byauthor{$key})) {
	# construct the category links that will appear at the top of a single-file index
	$authorlinks_1page .= "<a href=\"#authors" . $key . "\">" . $key . "</a> ";

	# construct the category links that will appear at the top of an alpha-file index
	$authorlinks_alpha .= "<a href=\"auth" . $fileconv{$key} . ".htm\">" . $key . "</a> ";

	# create the list of all records in this author category
	$authorlist .= "<br /><font color=\"#000000\"><b><a name=\"authors" . $key . "\">" . $key .
	    "</a></b></font><hr />" . join("<br />", @{$byauthor{$key}});
    }

    # if this key has records in the @bytitle array, create HTML for
    # this list of records, the index-to-index links for single and
    # alpha indexes are also created here
    if(exists($bytitle{$key})) {
	# the category links that will appear at the top of a single-file index
	$titlelinks_1page.= "<a href=\"#titles" . $key . "\">" . $key . "</a> ";

	# construct the category links that will appear at the top of an alpha-file index
	$titlelinks_alpha .= "<a href=\"titl" . $fileconv{$key} . ".htm\">" . $key . "</a> ";

	# create the list of all records in this title category
	$titlelist .= "<br /><font color=\"#000000\"><b><a name=\"titles" . $key . "\">" . $key .
	    "</a></b></font><hr />" . join("<br />", @{$bytitle{$key}});
    }
}

# make list of 1st-level directory links to allow user to browse 
# directories directly
opendir(DIR, $isotemp);
while($item = readdir(DIR)) {
    $fullpath = $isotemp . "/" . $item;
    if(-d $fullpath && $item ne "." && $item ne "..")
    {
	$dirlinks .= "<a href=\"" . $item . "/\">" . $item . "</a> ";
    }
}
closedir(DIR);

# call appropriate HTML-index creation function based on command-line argument
switch($format) {
    case "single"	{ singleindex() }
    case "multi"	{ multiindex() }
    case "alpha"	{ alphaindex() }
}

# create ISO
File::Path::mkpath(File::Basename::dirname($isopath)); 

# Note: sometimes the path already exists, so we will just continue to 
# the step of making the image.  That will exit if it fails
system("/usr/bin/genisoimage -quiet -input-charset=utf-8 -R -r -l -J -U -D -hide-rr-moved " 
       . " -V " . $volid . " -o" . $isopath . " " . $isotemp . " ");

# compute MD5 checksum of ISO
$md5output = `/usr/bin/md5sum $isopath`;
$md5output =~ m/^([0-9a-fA-F]{32}) /;
$md5sum = $1;

# get file size:
my $sizeoutput = `/sbin/isosize $isopath |cut -f2 -d' '`;

# create URL for user's ISO file
$isopath =~ s/$outwebroot//;
my $ftppath = "http://" . $domain . $isopath;
my $httppath = $ftppath;

# use email template to create an email notification for the user, 
# providing a URL for the ISO
open(TEMPLATE, "</home/gbnewby/pgiso/email_template.txt");
while($line = <TEMPLATE>) {
    $mailbody .= $line;
}
close(TEMPLATE);
$mailbody =~ s/_HTTP_URL_/$httppath/;
$mailbody =~ s/_FTP_URL_/$ftppath/;
$mailbody =~ s/_MD5_CHECKSUM_/$md5sum/;
$mailbody =~ s/_SIZEOUTPUT_/$sizeoutput/;

my $now_string = strftime "%a %b %e %H:%M:%S %Y", gmtime;

%mail = (
    To => "$email",
    From => 'Project Gutenberg ISO Creator <pgiso2014@pglaf.org>',
    Cc => "", # quells warnings in Sendmail.pm
    Bcc => "gbnewby\@pglaf.org", # escape @ here, but not in From, strangely
    Subject => "Your ISO is complete!  $email at $now_string",
    Message => $mailbody,
    Body => "", Text => "" # dupes to quell warnings in Sendmail.pm
    );

sendmail(%mail) or die $Mail::Sendmail::error;

exit;

#####
# Everything else is subroutines

# rdelete() does not work yet
#rdelete($isotemp);

sub singleindex {
    # construct HTML document
    my $HTML = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n" .
	"<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />" .
	"<title>EText Index</title></head>" .
	"<body text=\"#000000\" link=\"#b76b15\" vlink=\"#b76b15\"><br /><center>" .
	"Author: " . $authorlinks_1page . "<br />" .
	"Title: " . $titlelinks_1page . "<br /><br />" .
	"</center><table cellspacing=0 cellpadding=10 border=1 width=100%><tr><td width=50% valign=\"top\">".
	"<font color=\"#000000\"><b>BY AUTHOR</b></font><br />" .  $authorlist .
	"</td><td width=50% valign=\"top\">" .
	"<font color=\"#000000\"><b>BY TITLE</b></font><br />" . $titlelist .
	"</td></tr></table></body></html>";

    # write HTML to file, encoded as UTF-8
    open(HTMLINDEX, ">:utf8", $isotemp . "/index.htm");
    print HTMLINDEX $HTML;
    close(HTMLINDEX);
}

sub multiindex {
    # create arrays out of all HTML records, ignoring sort headers (#, A-Z, Unknown, Other)
    my @authorhtml = split("<br />\n", $authorlist); 
    my @titlehtml = split("<br />\n", $titlelist);

    # retrieve the number of records, applies to both @authorhtml and @titlehtml
    my $num_records = @authorhtml;

    # determine the number of HTML pages that will be needed, using record limit from command-line argument
    my $num_pages = 1;
    if ($num_records > $limit) {
	$num_pages = ($num_records / $limit) + 1;
    }

    # create a link for each HTML page
    for($i=1; $i <= $num_pages; $i++) {
	$authorlinks_multi .= "<a href=\"auth" . $i . ".htm\">" . $i . "</a> ";
	$titlelinks_multi .= "<a href=\"titl" . $i . ".htm\">" . $i . "</a> ";
    }

    # convert links to reference "indexes" subdirectory for the main index file
    $authorlinks_multi =~ s/href=\"/href=\"indexes\//g;
    $titlelinks_multi =~ s/href=\"/href=\"indexes\//g;

    # construct main index, consisting of only links to other pages
    my $index = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n" .
	"<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />" .
	"<title>EText Index</title></head>" .
	"<body text=\"#000000\" link=\"#b76b15\" vlink=\"#b76b15\"><br /><br /><center>" .
	"<table border=1 cellspacing=0 cellpadding=3 width=600><tr><td><b>Pages By Author:</b></td></tr>" .
	"<tr><td> " . $authorlinks_multi . "</td></tr></table><br />" .
	"<table border=1 cellspacing=0 cellpadding=3 width=600><tr><td><b>Pages By Title:</b></td></tr>" .
	"<tr><td> " . $titlelinks_multi . "</td></tr></table><br />" .
	"<table border=1 cellspacing=0 cellpadding=3 width=600><tr><td><b>Folders:</b></td></tr>" .
	"<tr><td> " . $dirlinks . "</td></tr></table></body></html>";

    # write main index to file, encoded as UTF-8
    open(HTMLINDEX, ">:utf8", $isotemp . "/index.htm");
    print HTMLINDEX $index;
    close(HTMLINDEX);

    # convert links back, because no other index file should reference 
    # the "indexes" subdirectory
    $authorlinks_multi =~ s/href=\"indexes\//href=\"/g;
    $titlelinks_multi =~ s/href=\"indexes\//href=\"/g;

    # make "indexes" directory to store all other index files
    mkdir $isotemp . "/indexes" || die "mkdir $isotemp failed: $!";

    # construct an HTML document for each page of both @authorhtml 
    # and @titlehtml
    my $numleft = $num_records;
    for(my $i=0; $i < $num_pages; $i++) {
	# calculate start and end points for record range of current page
	my $start = $i * $limit;
	# Is this the last (incompletely filled) page?
	my $end = $numleft - 1;
	if ($numleft > $limit) {
	    my $end = $start + $limit - 1;
	}

	# take a chunk of both @authorhtml and @titlehtml from the 
	# calculated range
	my @authorchunk = @authorhtml[$start..$end];
	my @titlechunk = @titlehtml[$start..$end];

	# increment $i for $index_num (so page $i == 0 appears as 
	# authorindex1.htm/titleindex1.htm)
	my $index_num = $i + 1;

	# remove empty records from chunks for the last page
	for(my $j=$limit-1; $j >= 0; $j--) {
	    if (defined ($authorchunk[$j])) {
		if($authorchunk[$j] eq "") {
		    pop(@authorchunk);
		}
	    }
	    if (defined ($titlechunk[$j])) {
		if($titlechunk[$j] eq "") {
		    pop(@titlechunk);
		}
	    }
	}

	# join the records from the chunk
	my $authorstring = join("<br />\n", @authorchunk);
	my $titlestring = join("<br />\n", @titlechunk);

	# convert etext file links to go back a directory (to move out of "indexes" directory)
	$authorstring =~ s/href=\"/href=\"..\//g;

	# construct HTML document for current page by author
	my $authorpage = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n" .
	    "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />" .
	    "<title>EText Index</title></head>" .
	    "<body text=\"#000000\" link=\"#b76b15\" vlink=\"#b76b15\"><br />" .
	    "<a href=\"../index.htm\">Main Index</a><br /><br />" .
	    "Pages By Author: " . $authorlinks_multi . "<br />" .
	    "Pages By Title: " . $titlelinks_multi . "<br /><br />" .
	    "<font color=\"#000000\"><b>BY AUTHOR - PAGE " . $index_num . "</b></font>" .
	    "<br />\n" . $authorstring . "</body></html>";

	# convert etext file links to go back a directory (to move out of "indexes" directory)
	$titlestring =~ s/href=\"/href=\"..\//g;

	# construct HTML document for current page by title
	my $titlepage = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n" .
	    "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />" .
	    "<title>EText Index</title></head>" .
	    "<body text=\"#000000\" link=\"#b76b15\" vlink=\"#b76b15\"><br />" .
	    "<a href=\"../index.htm\">Main Index</a><br /><br />" .
	    "Pages By Author: " . $authorlinks_multi . "<br />" .
	    "Pages By Title: " . $titlelinks_multi . "<br /><br />" .
	    "<font color=\"#000000\"><b>BY TITLE - PAGE " . $index_num . "</b></font>" .
	    "<br />\n" . $titlestring . "</body></html>";

	# write current page by author to file, encoded as UTF-8
	open(HTMLINDEX, ">:utf8", $isotemp . "/indexes/auth" . $index_num . ".htm");
	print HTMLINDEX $authorpage;
	close(HTMLINDEX);

	# write current page by title to file, encoded as UTF-8
	open(HTMLINDEX, ">:utf8", $isotemp . "/indexes/titl" . $index_num . ".htm");
	print HTMLINDEX $titlepage;
	close(HTMLINDEX);
    }
}

sub alphaindex {
    # convert links to reference "indexes" subdirectory for the main index file
    $authorlinks_alpha =~ s/href=\"/href=\"indexes\//g;
    $titlelinks_alpha =~ s/href=\"/href=\"indexes\//g;

    # construct main index, consisting of only links to other pages
    my $index = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n" .
	"<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />" .
	"<title>EText Index</title></head>" .
	"<body text=\"#000000\" link=\"#b76b15\" vlink=\"#b76b15\"><br /><br /><center>" .
	"<table border=1 cellspacing=0 cellpadding=3 width=600><tr><td><b>Pages By Author:</b></td></tr>" .
	"<tr><td> " . $authorlinks_alpha . "</td></tr></table><br />" .
	"<table border=1 cellspacing=0 cellpadding=3 width=600><tr><td><b>Pages By Title:</b></td></tr>" .
	"<tr><td> " . $titlelinks_alpha . "</td></tr></table><br />" .
	"<table border=1 cellspacing=0 cellpadding=3 width=600><tr><td><b>Folders:</b></td></tr>" .
	"<tr><td> " . $dirlinks . "</td></tr></table></body></html>";

    # write main index to file, encoded as UTF-8
    open(HTMLINDEX, ">:utf8", $isotemp . "/index.htm");
    print HTMLINDEX $index;
    close(HTMLINDEX);

    # convert links back, because no other index file should reference the "indexes" subdirectory
    $authorlinks_alpha =~ s/href=\"indexes\//href=\"/g;
    $titlelinks_alpha =~ s/href=\"indexes\//href=\"/g;

    # make "indexes" directory to store all other index files
    mkdir $isotemp . "/indexes" || die "mkdir $isotemp failed: $!";

    # create pages for each of the keys/arrays in @byauthor
    foreach my $letter(keys %byauthor) {
	# join records contained in current key/array
	my $authorstring = join("<br />\n", @{$byauthor{$letter}});

	# convert etext file links to go back a directory (to move out of "indexes" directory)
	$authorstring =~ s/href=\"/href=\"..\//g;

	# construct HTML for current key/array's page
	my $authorpage = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n" .
	    "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />" .
	    "<title>EText Index</title></head>" .
	    "<body text=\"#000000\" link=\"#b76b15\" vlink=\"#b76b15\"><br /><center>" .
	    "<a href=\"../index.htm\">Main Index</a><br /><br />" .
	    "Author: " . $authorlinks_alpha . "<br />" .
	    "Title: " . $titlelinks_alpha . "<br /><br />" .
	    "</center><font color=\"#000000\"><b>BY AUTHOR - " . $letter . "</b></font>" .
	    "<hr /><br />\n" . $authorstring . "</body></html>";

	# write current page to file, encoded as UTF-8
	open(HTMLINDEX, ">:utf8", $isotemp . "/indexes/auth" . $fileconv{$letter} . ".htm");
	print HTMLINDEX $authorpage;
	close(HTMLINDEX);
    }

    # create pages for each of the keys/arrays in @bytitle
    foreach my $letter(keys %bytitle) {
	# join records contained in current key/array
	my $titlestring = join("<br />\n", @{$bytitle{$letter}});

	# convert etext file links to go back a directory (to move out of "indexes" directory)
	$titlestring =~ s/href=\"/href=\"..\//g;

	# construct HTML for current key/array's page
	my $titlepage = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n" .
	    "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />" .
	    "<title>EText Index</title></head>" .
	    "<body text=\"#000000\" link=\"#b76b15\" vlink=\"#b76b15\"><br /><center>" .
	    "<a href=\"../index.htm\">Main Index</a><br /><br />" .
	    "Author: " . $authorlinks_alpha . "<br />" .
	    "Title: " . $titlelinks_alpha . "<br /><br />" .
	    "</center><font color=\"#000000\"><b>BY TITLE - " . $letter . "</b></font>" .
	    "<hr /><br />\n" . $titlestring . "</body></html>";

	# write current page to file, encoded as UTF-8
	open(HTMLINDEX, ">:utf8", $isotemp . "/indexes/titl" . $fileconv{$letter} . ".htm");
	print HTMLINDEX $titlepage;
	close(HTMLINDEX);
    }
}

# recursive delete function that will empty directories and remove them, 
# preserving base directory
sub rdelete {
    # assign location parameter
    my $location = $_[0];

    # if $location is a directory, empty it so it can be removed
    if (-d $location) {
	# open source directory and read its files into a local array 
	# to prevent directory handle scope problems
	opendir(DIR, $location);
	my @listing;
	while($item = readdir(DIR)) {
	    push(@listing, $item);
	}
	closedir(DIR);

	# for each item in the directory, append it to $location and 
	# call rdelete again
	foreach $item(@listing)	{
	    if($item ne "." && $item ne "..") {
		my $newloc = $location .= "/" . $item;
		rdelete($newloc);
	    }
	}
    }

    # delete file or empty directory
    unlink($location);
}

# recursive hard link function that will re-create directory structure and 
# hard link to files within
sub rlink {


# TODO: There's a problem where -h/images is not being followed.
# maybe I'm not handling a directory with files in it properly?

    # Policy: we skipped .txt in pgrdf_import.pl.  Here, we will use
    # the .utf8 but rename to .txt

    # assign function parameters
    my $sourcedir = $_[0];
    my $destdir = $_[1];
    my $file = $_[2];

# We're called like this: rlink (/data/ftp/mirrors/gutenberg,
#	/data/pgiso_temp, cache/generated/1001/pg1001.mobi)

    # Make the destination path:
#    print "mkpath " . ${destdir} . "/" . File::Basename::dirname(${file}) . "\n";
    my $makeme = ${destdir} . "/" . File::Basename::dirname(${file});
#    File::Path::mkpath(${destdir} . "/" . File::Basename::dirname(${file}));
    File::Path::mkpath($makeme);

    # Policy:
    my $destfile = $file;
    $destfile =~ s/\.utf8$//;

    # Symlink the file:
    link (${sourcedir} . "/" . ${file}, ${destdir} . "/" . ${destfile}) 
	|| die "link (${sourcedir}/${file}, ${destdir}/${destfile}) $!";

    # Policy: for HTML images (not all HTML has images)
    if ($makeme =~ /-h$/) {
#	print "** images: ";
	# Make a link, if there are images: 
	my $idir = "${sourcedir}/" . File::Basename::dirname(${file}) . "/images";
	if (-d $idir) {
	    # Make the images directory: 
#	    print "\t${makeme}/images\n";
	    File::Path::mkpath("${makeme}/images");

	    # Recurse, linking in all files (assume no more directories):
	    opendir (my $dh, $idir) || die "can't opendir images $idir: $!";
	    while (readdir($dh)) {
#		print ": image: $_\n";
		next if (/^\./); # skip directory
#		print ":image link:  ($idir/$_, ${makeme}/images/$_)";
		link ("$idir/$_", "${makeme}/images/$_") 

		    || die "image link failed: ($idir/$_, ${makeme}/images/$_) $!";
	    }
	    closedir($dh);
	}
    }

    return;

#
#
# Not used:
#
#
#


    # split file path into an array
    my @filepath_array = split("/", $file);

    # shift the first element off the array to be used in current instance 
    # of the function
    my $current = shift(@filepath_array);
#    print "current=$current\n";
#    return if (! $current);

    # join the file path back together and remove any trailing slash characters
    $file = join("/", @filepath_array);
    $sourcedir =~ s/\/$//;
    $destdir =~ s/\/$//;

    # add current file path element to the mirror source file path and 
    # ISO temp file path
    my $sourcefile = $sourcedir . "/" . $current;
###    my $destfile = $destdir . "/" . $current;

    # if $sourcefile is a directory and also the final element in the
    # location to be linked, re-create the directory, read the source
    # directory's contents and call rlink function again (we don't
    # want to read the contents of directories unless they are the
    # final element of the file path)

# gbn: this loop never gets called (?):
#    if (-d $sourcefile) {
    if (-d $sourcefile && @filepath_array == 0) {
#    if (-d $sourcefile && $#filepath_array == 0) {
	# re-create directory in the ISO temp directory
	print "mkdir1 $destfile\n";
	mkdir $destfile || die "mkdir $destfile failed: $!";

	# open source directory and read its files into a local array 
	# to prevent directory handle scope problems
	opendir(DIR, $sourcefile);
	my @listing;

	while($item = readdir(DIR)) {
	    print "\tpushed $item\n";
	    push(@listing, $item);
	}
	closedir(DIR);

	# call rlink again for all items read from the current directory
	foreach my $litem(@listing)	{
	    if($litem ne "" && $litem ne "." && $litem ne "..") {
#		if (-d $litem) {
		print "rlink recursing\t$sourcefile\t$destfile\t$litem\n"; 
		rlink($sourcefile, $destfile, $litem);
#		}
	    }
	}
    }
    # if $sourcefile is a file, simply create a hard link to it
    elsif (-f $sourcefile) {
	# Policy: rename .txt.utf8 to .txt
	$destfile =~ s/\.utf8$//;
#	print "\tlinking $sourcefile, $destfile\n";
	link $sourcefile, $destfile || die "linking $sourcefile, $destfile failed: $!";
    }

    # if $sourcefile is not a file, it must be a directory, so
    # re-create the directory and call rlink again
    elsif ($current ne "") {
	# We get called as we re-descend the directory tree:
	if (! -d $destfile) {
	    print "mkdir2 $destfile\n";
#	system "ls -R /data/pgiso_temp/";
	    mkdir $destfile || die "mkdir $destfile failed: $!";
	    rlink($sourcefile, $destfile, $file);
	}
    }
}
