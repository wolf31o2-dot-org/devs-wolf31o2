#!/usr/bin/perl

my $filter = '-Ov -Oe -Oq';

my $high_oid = '.1.3.6.1.4.1.789.1.2.2.7.0'; # miscHighCifsOps.0
my $low_oid = '.1.3.6.1.4.1.789.1.2.2.8.0'; # miscLowCifsOps.0
my $high_ops = `snmpget -v1 -c nagios $filter $ARGV[0] $high_oid`;
my $low_ops = `snmpget -v1 -c nagios $filter $ARGV[0] $low_oid`;

$high_ops = $high_ops << 32;
$total_ops = $high_ops | $low_ops;

print $total_ops."\n";
