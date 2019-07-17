#!/usr/bin/perl 
#

# this is a very basic example of parsing the catalog.rdf file

use XML::LibXML;
use Mysql;

# catalog.rdf is in utf-8 so stdout should be utf-8 too
binmode (STDOUT, ':utf8');

my $parser = XML::LibXML->new ();
$parser->keep_blanks (0);
 
my $doc = $parser->parse_file ('catalog.rdf');
my $db = Mysql->connect('localhost','gutenberg','pgrdf','sealab2021'); 

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

#$sql_query = "delete from etexts";
#$db->query($sql_query);

# output %books
#

while (my ($etext_no, $o) = each (%books)) {
   my $titles;
   my $authors;
   my $files;

   foreach (@{$o->{'titles'}}) {
       $titles .= "$_;";
   }
   chop($titles); 

   foreach (@{$o->{'authors'}}) {
       $authors .= "$_;";
   }
   chop($authors);

   foreach (@{$o->{'files'}}) {
       $files .= "$_;";
   }
   chop($files);

#   $quoted_etext_no = quotemeta($etext_no);
#   $quoted_titles = quotemeta($titles);
#   $quoted_authors = quotemeta($authors);
#   $quoted_files = quotemeta($files);

   $tabfile .= "$etext_no\t$titles\t$authors\t$files\n";

#   $sql_query = "insert into etexts values" .
#                "('$quoted_etext_no','$quoted_titles','$quoted_authors','$quoted_files')";
#   $db->query($sql_query);
}

open(TABFILE, ">catalog.txt");
print TABFILE $tabfile;
close(TABFILE);
