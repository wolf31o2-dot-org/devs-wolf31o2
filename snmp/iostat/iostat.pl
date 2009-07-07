#!/usr/bin/env perl

# iostat.pl SNMP parser
# Mark Round, Email : cacti@markround.com
# Based heavily on the awesome Bind9 stats parser written by Cory Powers
#
# NOTE: the .1.3.6.1.3.1 OID in this script uses an "experimental" sequence,
# which may not be unique in your organisation[1]. You should probably change
# this to something else, perhaps using your own private OID.
#
# [1]=http://www.alvestrand.no/objectid/1.3.6.1.3.html
#
# USAGE
# -----
# See the README which should have been included with this file.
#
# CHANGES
# -------
# 14/10/2008 - Version 1.0 - Initial release, Linux iostat only. Solaris etc.
#                            coming in next revision!
#

use strict;

use constant debug => 0;
my $base_oid = ".1.3.6.1.4.1.2021.1";
my $iostat_cache = "/tmp/iostat.cache";
my $req;
my %stats;
my $devices;

process();

my $mode = shift(@ARGV);
if ( $mode eq "-g" ) {
    $req = shift(@ARGV);
    getoid($req);
}
elsif ( $mode eq "-n" ) {
    $req = shift(@ARGV);
    my $next = getnextoid($req);
    getoid($next);
}
else {
    $req = $mode;
    getoid($req);
}

sub process {
    $devices = 1;
    open( IOSTAT, $iostat_cache )
      or die("Could not open iostat cache $iostat_cache : $!");

    my $header_seen = 0;

    while (<IOSTAT>) {
        if (/^Device/) {
            $header_seen++;
            next;
        }
        next if ( $header_seen < 2 );
        next if (/^$/);

/^([a-z0-9\-\/]+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)/;

        $stats{"$base_oid.1.$devices"}  = $devices;	# index
        $stats{"$base_oid.2.$devices"}  = $1;		# device name
        $stats{"$base_oid.3.$devices"}  = $2;		# rrqm/s
        $stats{"$base_oid.4.$devices"}  = $3;		# wrqm/s
        $stats{"$base_oid.5.$devices"}  = $4;		# r/s
        $stats{"$base_oid.6.$devices"}  = $5;		# w/s
        $stats{"$base_oid.7.$devices"}  = $6;		# rkB/s
        $stats{"$base_oid.8.$devices"}  = $7;		# wkB/s
        $stats{"$base_oid.9.$devices"}  = $8;		# avgrq-sz
        $stats{"$base_oid.10.$devices"} = $9;		# avgqu-sz
        $stats{"$base_oid.11.$devices"} = $10;		# await
        $stats{"$base_oid.12.$devices"} = $11;		# svctm
        $stats{"$base_oid.13.$devices"} = $12;		# %util

        $devices++;
    }

}

sub getoid {
    my $oid = shift(@_);
    print "Fetching oid : $oid\n" if (debug);
    if ( $oid =~ /^$base_oid\.(\d+)\.(\d+).*/ && exists( $stats{$oid} ) ) {
        print $oid. "\n";
        if ( $1 == 1 ) {
            print "integer\n";
        }
        else {
            print "string\n";
        }

        print $stats{$oid} . "\n";
    }
}

sub getnextoid {
    my $first_oid = shift(@_);
    my $next_oid  = '';
    my $count_id;
    my $index;

    if ( $first_oid =~ /$base_oid\.(\d+)\.(\d+).*/ ) {
        print("getnextoid($first_oid): index: $2, count_id: $1\n") if (debug);
        if ( $2 + 1 >= $devices ) {
            $count_id = $1 + 1;
            $index    = 1;
        }
        else {
            $index    = $2 + 1;
            $count_id = $1;
        }
        print(
            "getnextoid($first_oid): NEW - index: $index, count_id: $count_id\n"
        ) if (debug);
        $next_oid = "$base_oid.$count_id.$index";
    }
    elsif ( $first_oid =~ /$base_oid\.(\d+).*/ ) {
        $next_oid = "$base_oid.$1.1";
    }
    elsif ( $first_oid eq $base_oid ) {
        $next_oid = "$base_oid.1.1";
    }
    print("getnextoid($first_oid): returning $next_oid\n") if (debug);
    return $next_oid;
}
