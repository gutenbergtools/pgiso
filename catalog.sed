#!/bin/sh
# Transform the XML/RDF catalog for use by pgrdf_input.pl.  This 
# has the effect of consolidating the structure similar to the previous
# catalog.rdf file.  gbn 2014/08/15

 /bin/sed '/^<rdf:RDF$/d' | \
 /bin/sed '/^   xmlns:cc="http:\/\/web.resource.org\/cc\/"$/d' | \
 /bin/sed '/^   xmlns:dcam="http:\/\/purl.org\/dc\/dcam\/"$/d' | \
 /bin/sed '/^   xmlns:dcterms="http:\/\/purl.org\/dc\/terms\/"$/d' | \
# This one has some variations in placement:
 /bin/sed '/xmlns:marcrel="http:\/\/www.loc.gov\/loc.terms\/relators\/"$/d' | \
 /bin/sed '/xmlns:marcrel="http:\/\/id.loc.gov\/vocabulary\/relators\/"$/d' | \
 /bin/sed '/^   xmlns:pgterms="http:\/\/www.gutenberg.org\/2009\/pgterms\/"$/d' | \
 /bin/sed '/^   xmlns:rdf="http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#"$/d' | \
 /bin/sed '/^   xml:base="http:\/\/www.gutenberg.org\/">$/d' | \
 /bin/sed '/^  <cc:Work rdf:about="feeds\/catalog.rdf">$/d' | \
 /bin/sed '/^    <cc:license rdf:resource="http:\/\/www.gnu.org\/licenses\/gpl.html"\/>$/d' | \
#  /bin/sed '/^  <\/cc:Work>$/d' | \
 /bin/sed '/^<\/rdf:RDF>$/d' | \
 /bin/sed '/^<rdf:RDF xml:base="http:\/\/www.gutenberg.org\/"$/d' | \
 /bin/sed '/^  xmlns:cc="http:\/\/web.resource.org\/cc\/"$/d' | \
 /bin/sed '/^  xmlns:dcterms="http:\/\/purl.org\/dc\/terms\/"$/d' | \
 /bin/sed '/^  xmlns:dcam="http:\/\/purl.org\/dc\/dcam\/"$/d' | \
 /bin/sed '/^  xmlns:pgterms="http:\/\/www.gutenberg.org\/2009\/pgterms\/"$/d' | \
 /bin/sed '/^  xmlns:rdf="http:\/\/www.w3.org\/1999\/02\/22-rdf-syntax-ns#"$/d' | \
 /bin/sed '/^>$/d' | \
 /bin/sed '/^<?xml version="1.0" encoding="UTF-8" ?>$/d' | \
 /bin/sed '/^<?xml version="1.0" encoding="utf-8"?>/d' | \
 /bin/sed '/^<!DOCTYPE rdf:RDF \[$/d' | \
 /bin/sed '/^  <!ENTITY pg  "Project Gutenberg">$/d' | \
 /bin/sed '/^  <!ENTITY lic "http:\/\/www.gutenberg.org\/license">$/d' | \
 /bin/sed '/^  <!ENTITY f   "http:\/\/www.gutenberg.org\/">$/d' | \
 /bin/sed '/^]>$/d' | \
 /bin/sed 's/^  <pgterms:ebook rdf/\n  <pgterms:ebook rdf/' | \
 /bin/sed 's/^      <pgterms:file rdf/\n      <pgterms:file rdf/'
