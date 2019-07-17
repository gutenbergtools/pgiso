#!/usr/bin/perl 
#

# this is a very basic example of parsing the catalog.rdf file

use XML::LibXML;

# catalog.rdf is in utf-8 so stdout should be utf-8 too
binmode (STDOUT, ':utf8');

my $parser = XML::LibXML->new ();
$parser->keep_blanks (0);
 
my $doc = $parser->parse_file ('catalog.rdf');

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
        push @{$books{$etext_no}->{'files'}}, $filenode->getAttribute ('about')
;
    }
}
@filenodes = undef; # release some memory
$doc = undef;

# output %books
#

while (my ($etext_no, $o) = each (%books)) {
    print ("$etext_no\n");
    foreach (@{$o->{'titles'}}) {
        print "<title>$_</title>\n";
    }
    foreach (@{$o->{'authors'}}) {
        print "<creator>$_</creator>\n";
    }
    foreach (@{$o->{'files'}}) {
        print "<identifier>$_</identifier>\n";
    }
    print "\n";
}

