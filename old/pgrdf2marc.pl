#!/usr/local/bin/perl -w
# 
# pgrdf2marc.pl converts one or more items from the Project Gutenberg RDF
# catalog into MARC format record(s).
#
# Detailed POD-style documentation is at the end of this file.
#
#-----------------------------------------------------------------------
# Configurables:

# Organisation code
my $org = 'PGUSA';

my $publisher = 'Project Gutenberg';

# set this to 1 to dump out the MARC in text format, followed by the RDF
$DEBUG = 0;

#-----------------------------------------------------------------------
# input record separator is blank line
$/ = "\n\n";

# %cat is a temporary hash for the data extracted from RDF
my %cat;
# @rec is a temporary array used to build a MARC record
my @trec;

#populate a hash to map 2-letter language codes to 3-letter version
my %map639;
&ISO639;

while (<>) {

    # convert commonly used character entities
    s/ &amp; / and /sg;
    s/&amp;/&/sg;
    s/&#x2014;/--/sg;

    if (&parse_rdf($_)) {
	&build_trec();
	if ($DEBUG) {
	    # dump the RDF
	    print "$_\n"; 
	    # dump the MARC (pretty-printed)
	    foreach (@trec) { print "$_\n"; }
	    print "\n"; 
	} else {
	    $marc = &array2marc(@trec);
	    print $marc;
	}
    }
}

exit(0);

sub parse_rdf {
    # parse an rdf entry and store the data in our catalogue hash
    #my ($rdf) = @_;

    # clear %cat;
    %cat = ();
    
    # record must have an id ...
    if (/rdf:ID="etext(\d+)"/) { $cat{id} = $1; } else { return 0; }

    # ... and a title ...
    if (/<dc:title.*?>(.*)<\/dc:title>/s) {
	$cat{title} = [ &split_field($1) ];
	# N.B. Allow for mulitple titles!
	# The first title becomes the main title (arbitrary?)
    } else {
	return 0;
    }

    if (/<dc:created>(.*)<\/dc:created>/) {
	$cat{created} = $1;
    }

    if (/<dc:language>(.*)<\/dc:language>/) {
	$cat{language} = $1;
    }

    if (/<dc:type>(.*)<\/dc:type>/) {
	$cat{type} = $1;
    }

    if (/<dc:rights>(.*)<\/dc:rights>/) {
	$cat{rights} = $1;
    }

    if (/<dc:creator>(.*)<\/dc:creator>/s) {
	$cat{author} = [ &split_field($1) ];
    }

    if (/<dc:contributor>(.*)<\/dc:contributor>/s) {
	$cat{contributor} = [ &split_field($1) ];
    }

    if (/<dcterms:LCC>(.*)<\/dcterms:LCC>/s) {
	$cat{call} = [ &split_field($1) ];
    }

    if (/<dcterms:LCSH>(.*)<\/dcterms:LCSH>/s) {
	$cat{subject} = [ &split_field($1) ];
    }

    if (/<dc:alternative.*?>(.*)<\/dc:alternative>/is) {
	$cat{alternative} = [ &split_field($1) ];
    }

    if (/<dc:tableOfContents.*?>(.*)<\/dc:tableOfContents>/is) {
	$cat{contents} = [ &split_field($1) ];
    }

    # experimental kludge to determine genre 
    # genre is the 'Literary form' specified in the 008 character pos. 33
    GENRE: {
	if (/(poems|poetry)/i) { $cat{genre} = 'p'; last; }
	if (/other (stories|tales)/i) { $cat{genre} = 'j'; last; }
	if (/essays/i) { $cat{genre} = 'e'; last; }
	if (/letters/i) { $cat{genre} = 'i'; last; }
	if (/plays/i) { $cat{genre} = 'd'; last; }
	$cat{genre} = '|';
    }

    return 1;
}

sub split_field {
    # split a (possibly) multivalued field, returning values as an array.
    my $field = shift;
    my @array;
    if ($field =~ /rdf:Bag/i) {
        while ($field =~ s/<rdf:li>(.*)<\/rdf:li>//) {
            push @array, $1;
        }
    } else {
        push @array, $field;
    }
    return @array;
}

sub build_trec {
    # use %cat to build a text array of the MARC record
    @trec = ();

    my ($ind2, $resp, $pubyear, $temp);
    my ($id, $created, $language, $genre, $type, $rights, $title);
    my (@titles, @authors, @alternative, @contribs, @subjects, @call,
    @contents);

    $id		= $cat{id};
    
    $created		= $cat{created};
    # created is formatted as "yyyy-mm-dd" ...
    # extract a publication date (year only)
    $pubyear = substr $created, 0, 4;
    # reformat creation date as "yymmdd" for 008
    $created =~ s/-//g;
    $created = substr $created, 2, 6;
    # (N.B. the 008 date is the create date for the MARC record.
    # We're pretending that this is the same as the etext. Otherwise,
    # this could just be today's date.)

    $language    	= $cat{language};
    $language = 'en' if (! defined $language);

    $genre 		= $cat{genre};
    $rights 		= $cat{rights};

    $type 		= $cat{type};
    unless ($type) { $type = "Electronic text"; }
    if ($type =~ /(.*), (.*)/) { $type = "$2 $1"; }

    @titles    	= @{ $cat{title} };
    # There may be multiple titles; the first become the main title
    $title    	= shift @titles;

    @authors    	= @{ $cat{author} } if $cat{author};
    @contribs		= @{ $cat{contributor} } if $cat{contributor};
    @subjects   	= @{ $cat{subject} } if $cat{subject};
    @call    		= @{ $cat{call} } if $cat{call};
    @alternative	= @{ $cat{alternative} } if $cat{alternative};
    @contents    	= @{ $cat{contents} } if $cat{contents};

    # start building the marc text array
    push @trec, "LDR 00568cam a22001693a 4500";

    push @trec, "000 $id";
    push @trec, "003 $org";
    
# omit the 005 -- to facilitate record matching for change management
#-- # it would be preferable to use the date last modified in 005,
#-- # but we don't have it.
#-- push @trec, sprintf "005 %s", &timestamp;

    push @trec, sprintf "008 %6ss%4s||||xxu|||||s|||||000 %1s %3s d",
        $created, $pubyear, $genre, $map639{$language};

    push @trec, sprintf "040   |a%s|b%s", $org, $map639{$language};
    push @trec, "042   |adc";

    foreach (@call) {
        push @trec, sprintf "050  4|a%s", $_;
    }

    if (@authors) {
        my ($name, $d, $q) = &munge_author( shift @authors );
	unless ($name =~ /(Various|Anonymous)/i) {
	    $temp = sprintf "100 1 |a%s", $name;
	    $temp .= "|q$q" if $q;
	    $temp .= ",|d$d" if $d;
	    push @trec, $temp;
	}
    }

    # set non-filing indicator for title.
    # (this is crude, and only works for english. Can we do better?)
    $ind2 = 0;
    $ind2 = 4 if $title =~ /^The /;
    $ind2 = 3 if $title =~ /^An /;
    $ind2 = 2 if $title =~ /^A /;

    # remove break tags!
    $title =~ s/<br \/>/ /g;

    $resp = &resp_stmt();
    if ($resp) {
        push @trec, sprintf
        "245 1%1d|a%s |h[electronic resource] /|c%s",
        $ind2, $title, $resp;
    } else {
        push @trec, sprintf
        "245 1%1d|a%s |h[electronic resource]", $ind2, $title;
    }

    push @trec, "260   |b$publisher,|c$pubyear";
    push @trec, "500   |aProject Gutenberg";

    # Contents note
    if ($#contents > 0) { # more than one element in contents!
	push @trec, sprintf "505 0 |a%s", (join '--', @contents);
    }

    # Rights management / copyright statement
    if ($rights) {
	push @trec, "506   |a$rights";
    } else {
	push @trec, "506   |aFreely available.";
    }
    push @trec, "516   |a$type";

    # Subject headings
    # Note: we're using the 653 "uncontrolled terms" field, not LCSH
    foreach (@subjects) {
        push @trec, sprintf "653  0|a%s", $_;
    }

    foreach (@authors) {
        ($name, $d, $q, $role) = &munge_author( $_ );
	$temp = "700 1 |a$name";
	$temp .= "|q$q" if $q;
	$temp .= ",|d$d" if $d;
	$temp .= ",|e$role" if $role;
	push @trec, $temp;
    }

    foreach (@contribs) {
        ($name, $d, $q, $role) = &munge_author( $_ );
	$temp = "700 1 |a$name";
	$temp .= "|q$q" if $q;
	$temp .= ",|d$d" if $d;
	$temp .= ",|e$role" if $role;
	push @trec, $temp;
    }

    foreach (@titles) {
	s/<br \/>/ /g;
        push @trec, sprintf "740 0 |a%s", $_;
    }

    foreach (@alternative) {
	s/<br \/>/ /g;
        push @trec, sprintf "740 0 |a%s", $_;
    }

    # if only one element in contents, treat as added title
    if ($#contents == 0) {
	push @trec, sprintf "740 0 |a%s", $contents[0];
    }

    push @trec, sprintf "830  0|aProject Gutenberg|v%d", $id;
    push @trec, sprintf "856 40|uhttp://www.gutenberg.org/etext/%d", $id;

    push @trec, "856 42|uhttp://www.gutenberg.org/license|3Rights"
	unless $rights;

}

sub munge_author {
    my $name = shift;
    my ($role, $q, $d);
    # extract the dates (if any) and discard from name
    # dates are assumed as anything between () starting with a digit
    if ($name =~ s/ \(([1-9-].+)\)//) { $d = $1; }
    # extract and discard any expanded forenames -- these will be in ()
    if ($name =~ s/ (\(.+\))//) { $q = $1; }
    # extract and discard role -- between []
    if ($name =~ s/ \[(.+)\]//) { $role = $1; }
    return ($name, $d, $q, $role);
}

sub resp_stmt {
    # generate a statement of responsibility from the author fields
    my @authors		= @{ $cat{author} } if $cat{author};
    my @contribs	= @{ $cat{contributor} } if $cat{contributor};
    my ($author, $name, $role, $resp);
    $resp = '';

    # first author ...
    $author = shift @authors; 
    if ($author) {
        ($name, undef) = munge_author( $author );
        if ($name =~ /(.*?), (.*)/) { $name = "$2 $1"; }
        $resp = "by $name";
    }
    # followed by any additional authors ...
    while ($author = shift @authors) {
        ($name, undef) = munge_author( $author );
        if ($name =~ /(.*?), (.*)/) { $name = "$2 $1"; }
	if (@authors) {
	    $resp .= ", $name";
	} else {
	    $resp .= " and $name";
	}
    }
    # followed by contributors ...
    foreach $author (@contribs) {
        ($name, undef, undef, $role) = munge_author( $author );
        if ($name =~ /(.*?), (.*)/) { $name = "$2 $1"; }
	ROLE: {
	if ($role =~ /edit/i) { $resp .= "; edited by $name"; last; }
	if ($role =~ /Trans/i) { $resp .= "; translated by $name"; last; }
	if ($role =~ /Illus/i) { $resp .= "; illustrated by $name"; last; }
        $resp .= "; $name";
	if ($role) { $resp .= " ($role)"; }
	}
    }
    $resp =~ s/\s+/ /g;
    return $resp;
}

sub timestamp {
    my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst)
    = localtime(time);
    return sprintf "%4d%02d%02d%02d%02d%02d.0",
        $year+1900, $mon+1, $mday, $hour, $min, $sec;
}

sub array2marc {
    my @trec = @_;

    # initialise stuff
    my $offset = 0;
    my $dir = '';
    my $data = '';

    # default pattern for leader
    my $ldrpat = "%05dnas  22%05duu 4500";

    # if a leader is included, build the pattern from that ...
    if ( $trec[0] =~ /^LDR/ ) { # leader codes
	$line = shift(@trec);
	# use the leader to create a pattern for building the leader later on
	# only the RS, MC, BLC, EL, DCC and Linked are used
	$ldrpat = '%05d'.substr($line,9,5).'22%05d'.substr($line,21,3).'4500';
    }

    # process all the tags in sequence
    foreach $line ( @trec ) {

	# build the directory and data portions
	$tag = substr($line, 0, 3);
	$field = substr($line, 4);		# get the data for the tag
	unless ($tag lt '010') {
	    $field =~ tr/\|/\037/s;	# change subfield delimiter(s)
	}
	$field =~ s/$/\036/;	# append a field terminator
	$fldlen = length($field);
	$dir .= sprintf("%3s%04d%05d",$tag,$fldlen,$offset);
	$offset += $fldlen;
	$data .= $field;
    }

    # append a field terminator to the directory
    $dir =~ s/$/\036/;

    # append the record terminator
    $data =~ s/$/\035/;

    # compute lengths
    $base = length($dir) + 24;	# base address of data
    $lrl = $base + length($data);	# logical record length

    # return the complete MARC record
    return (sprintf $ldrpat,$lrl,$base)			# leader
	    . $dir					# directory
	    . $data;					# data

}

sub ISO639 {

# ISO 639 Language Codes

# populate a hash mapping 639-1 (2-letter) codes to 639-2 (3-letter) codes

%map639 = qw(
	ab  abk
	aa  aar
	af  afr
	sq  alb
	am  amh
	ar  ara
	hy  arm
	as  asm
	ay  aym
	az  aze
	ba  bak
	eu  baq
	bn  ben
	bh  bih
	bi  bis
	be  bre
	bg  bul
	my  bur
	be  bel
	ca  cat
	zh  chi
	co  cos
	hr  scr
	cs  cze
	da  dan
	nl  dut
	dz  dzo
	en  eng
	eo  epo
	et  est
	fo  fao
	fj  fij
	fi  fin
	fr  fre
	fy  fry
	gl  glg
	ka  geo
	de  ger
	el  gre
	kl  kal
	gn  grn
	gu  guj
	ha  hau
	he  heb
	hi  hin
	hu  hun
	is  ice
	id  ind
	ia  ina
	iu  iku
	ik  ipk
	ga  gle
	it  ita
	ja  jpn
	jv  jav
	kn  kan
	ks  kas
	kk  kaz
	km  khm
	rw  kin
	ky  kir
	ko  kor
	ku  kur
	oc  oci
	lo  lao
	la  lat
	lv  lav
	ln  lin
	lt  lit
	mk  mac
	mg  mlg
	ms  may
	ml  mlt
	mi  mao
	mr  mar
	mo  mol
	mn  mon
	na  nau
	ne  nep
	no  nor
	or  ori
	om  orm
	pa  pan
	fa  per
	pl  pol
	pt  por
	ps  pus
	qu  que
	rm  roh
	ro  rum
	rn  run
	ru  rus
	sm  smo
	sg  sag
	sa  san
	sr  scc
	sh  scr
	sn  sna
	sd  snd
	si  sin
	ss  ssw
	sk  slo
	sl  slv
	so  som
	st  sot
	es  spa
	su  sun
	sw  swa
	sv  swe
	tl  tgl
	tg  tgk
	ta  tam
	tt  tat
	te  tel
	th  tha
	bo  tib
	ti  tir
	to  tog
	ts  tso
	tn  tsn
	tr  tur
	tk  tuk
	tw  twi
	ug  uig
	uk  ukr
	ur  urd
	uz  uzb
	vi  vie
	vo  vol
	cy  wel
	wo  wol
	xh  xho
	yi  yid
	yo  yor
	za  zha
	zu  zul
);

}

__END__

=head1 NAME

pgrdf2marc.pl

=head1 DESCRIPTION

pgrdf2marc.pl converts one or more items from the Project Gutenberg RDF
catalog into MARC format record(s).

The RDF is read from STDIN, and the MARC output to STDOUT.

Dublin Core tags used in the RDF are:

    dc:title
    dc:alternative
    dc:creator
    dc:contributor
    dc:tableOfContents
    dc:publisher
    dc:rights
    dc:language
    dc:created
    dc:type
    dcterms:LCSH
    dcterms:LCC


A MARC record is simply an ASCII string of arbitrary length.

=head2 MARC record structure

    Leader: start: 0 length: 24
	Base Address (start of data): start: 12 length: 5
    Directory: start: 24, length: (base - 24)
	Tag number: 3 bytes
	data length: 4 bytes
	data offset: 5 bytes

    Subfields begin with 0x1f
    Fields end with 0x1e
    Records end with 0x1d

=head2 Array element structure

The conversion process makes use of a simple array structure,
where each array element contains the tag and data for a single MARC
field, separated by a single space.

	cols. 0-2 : tag number
	col.  3   : blank
	cols. 4-5 : indicators
	cols. 6-  : tag data

e.g.

	245 10|aSome title|h[GMD]

The '|' character is used to represent MARC subfield separators (0x1f).

=head1 REFERENCES

MARC Standards, http://www.loc.gov/marc/

Dublin Core/MARC/GILS Crosswalk, http://www.loc.gov/marc/dccross.html

=head1 VERSION

Version 2004-11-25

=head1 AUTHOR

Steve Thomas <stephen.thomas@adelaide.edu.au>

=head1 LICENCE

Copyright (c) 2004 Steve Thomas <stephen.thomas@adelaide.edu.au>

Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

=cut

