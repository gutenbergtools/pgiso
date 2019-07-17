#!/usr/bin/perl 
#

# this is a very basic example of parsing the catalog.rdf file

use XML::LibXML;
use Mysql;

$path = "/home/cstephen/pgiso/pgrdf/";

# download and decompress rdf catalog file
system("rm " . $path . "catalog.rdf.bz2 " . $path . "catalog.rdf 2> /dev/null");
system("wget -q -O " . $path . "catalog.rdf.bz2 http://www.gutenberg.org/feeds/catalog.rdf.bz2");
system("bunzip2 " . $path . "catalog.rdf.bz2");

# catalog.rdf is in utf-8 so stdout should be utf-8 too
binmode (STDOUT, ':utf8');

my $parser = XML::LibXML->new ();
$parser->keep_blanks (0);
 
my $doc = $parser->parse_file ($path . 'catalog.rdf');
my $db = Mysql->connect('localhost','gutenberg','pgdbupdate','sealab2021'); 

my %books;

# parse XML into %books data structure
#
# parse book nodes

my @booknodes = $doc->findnodes ('/rdf:RDF/pgterms:etext');

foreach my $booknode (@booknodes) {
    # this is a book description node
    my $etext_no = $booknode->getAttribute ('ID');
    $etext_no =~ s/^etext//;
    my $o = {};

    foreach $title ($booknode->findnodes ('dc:title//text()')) {
        push @{$o->{'titles'}}, $title->textContent;
    }
    foreach $creator ($booknode->findnodes ('dc:creator//text()')) {
        push @{$o->{'authors'}}, $creator->textContent;
    }
    foreach $language ($booknode->findnodes ('dc:language//text()')) {
        push @{$o->{'language'}}, $language->textContent;
    }
    $books{$etext_no} = $o;
}
@booknodes = undef; # release some memory

# parse file nodes

my @filenodes = $doc->findnodes ('/rdf:RDF/pgterms:file');

foreach my $filenode (@filenodes) {
    foreach my $n ($filenode->findnodes ('dcterms:isFormatOf')) {
        # this is a file description node
        my $etext_no = $n->getAttribute ('resource');
        $etext_no =~ s/^\#etext//;
        push @{$books{$etext_no}->{'files'}}, $filenode->getAttribute ('about');
    }
}
@filenodes = undef; # release some memory
$doc = undef;

# delete old records
$sql_query = "delete from etexts";
$db->query($sql_query);

# output %books
#

 # prepare MySQL for UTF-8 encoded data
 $sql_query = "SET NAMES 'utf8'";
 $db->query($sql_query);

while (my ($etext_no, $o) = each (%books)) {
   my $titles;
   my $authors;
   my $language;
   my $files;

   foreach (@{$o->{'titles'}}) {
       $titles .= "$_;";
   }
   chop($titles); 

   foreach (@{$o->{'authors'}}) {
       $authors .= "$_;";
   }
   chop($authors);

   foreach (@{$o->{'language'}}) {
       $language .= "$_;";
   }
   chop($language);

   foreach (@{$o->{'files'}}) {
       $files .= "$_;";
   }
   chop($files);

   # prepare variables to be entered into MySQL 
   $quoted_etext_no = quotemeta($etext_no);
   $quoted_titles = quotemeta($titles);
   $quoted_authors = quotemeta($authors);
   $quoted_language = quotemeta($language);
   $quoted_files = quotemeta($files);

   # enter values into MySQL
   $sql_query = "INSERT INTO etexts VALUES" .
     "('$quoted_etext_no','$quoted_titles','$quoted_authors','$quoted_language','$quoted_files')";
print $sql_query . "\n";
   $db->query($sql_query);
}

# system("/home/cstephen/pgiso/pgformatsize/pgformatsize.pl");
