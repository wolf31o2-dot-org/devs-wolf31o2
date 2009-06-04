#!/usr/bin/perl

my $filter = '-Ov -Oe -Oq';

my $high_oid = '.1.3.6.1.4.1.789.1.2.2.17.0'; # miscHighDiskWriteBytes.0
my $low_oid = '.1.3.6.1.4.1.789.1.2.2.18.0'; # miscLowDiskWriteBytes.0
my $high_bytes = `snmpget -v1 -c nagios $filter $ARGV[0] $high_oid`;
my $low_bytes = `snmpget -v1 -c nagios $filter $ARGV[0] $low_oid`;

$high_bytes = $high_bytes << 32;
$total_bytes = $high_bytes | $low_bytes;

print $total_bytes."\n";
