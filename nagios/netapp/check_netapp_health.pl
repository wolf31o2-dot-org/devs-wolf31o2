#!/usr/bin/perl -w
# $Id: check_netapp_health.pl 1216 2008-04-24 12:51:18Z wpreston $

=pod

=head1 COPYRIGHT

 
This software is Copyright (c) 2008 NETWAYS GmbH, William Preston
                               <support@netways.de>

(Except where explicitly superseded by other copyright notices)

=head1 LICENSE

This work is made available to you under the terms of Version 2 of
the GNU General Public License. A copy of that license should have
been provided with this software, but in any event can be snarfed
from http://www.fsf.org.

This work is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
02110-1301 or visit their web page on the internet at
http://www.fsf.org.


CONTRIBUTION SUBMISSION POLICY:

(The following paragraph is not intended to limit the rights granted
to you to modify and distribute this software under the terms of
the GNU General Public License and is only of importance to you if
you choose to contribute your changes and enhancements to the
community by submitting them to NETWAYS GmbH.)

By intentionally submitting any modifications, corrections or
derivatives to this work, or any other work intended for use with
this Software, to NETWAYS GmbH, you confirm that
you are the copyright holder for those contributions and you grant
NETWAYS GmbH a nonexclusive, worldwide, irrevocable,
royalty-free, perpetual, license to use, copy, create derivative
works based on those contributions, and sublicense and distribute
those contributions and any derivatives thereof.

Nagios and the Nagios logo are registered trademarks of Ethan Galstad.

=head1 NAME

check_netapp_health

=head1 SYNOPSIS

Retrieves the status of a NetApp and converts the resulting error code

=head1 OPTIONS

check_netapp_health [options] <hostname> <SNMP community>

=over

=item   B<--warning>

warning level (not used)

=item   B<--critical>

critical level (not used)

=item   B<--timeout>

how long to wait for the reply (default 30s)

=back

=head1 DESCRIPTION

This plugin checks the status of a NetApp Appliance using SNMP

It doesn't require the NETWORK-APPLIANCE-MIB (the OID is
hardcoded into the script)


=cut

use Getopt::Long;
use Pod::Usage;
use lib '/usr/nagios/libexec/';
##use lib '/usr/lib/nagios/plugins/';
use utils qw(%ERRORS);
use Net::SNMP;

sub nagexit($$);

$warning = 40;
$critical = 20;

my %OIDS = (	miscGlobalStatus.0		=>	'.1.3.6.1.4.1.789.1.2.2.4.0',
		miscGlobalStatusMessage.0	=>	'.1.3.6.1.4.1.789.1.2.2.25.0');


my %ErrorMap = (	1	=>	'WARNING',
			2	=>	'UNKNOWN',
			3	=>	'OK',
			4	=>	'WARNING',
			5	=>	'CRITICAL',
			6	=>	'CRITICAL');

# check the command line options
GetOptions('help|?' => \$help,
           't|timeout=i' => \$tout,
           'w|warn|warning=i' => \$warning,
           'c|crit|critical=i' => \$critical);

if ($#ARGV!=1) {$help=1;} # wrong number of command line options
# pod2usage( -verbose => 99, -sections => "NAME|COPYRIGHT|SYNOPSIS|OPTIONS") if $help;
pod2usage(1) if $help;

my $tout = 15 if (!defined($tout));
my $host = shift;
my $community = shift;

$SIG{'ALRM'} = sub { nagexit('CRITICAL', "Timeout trying to reach device $host") };

alarm($tout);

my ($session, $error) = Net::SNMP->session(
	-hostname	=>	$host,
	-community	=>	$community);
if (!defined($session)) { nagexit('CRITICAL', "Failed to reach device $host") };


my $result = $session->get_request(
	-varbindlist	=>	[$OIDS{miscGlobalStatus.0},$OIDS{miscGlobalStatusMessage.0}]);
if (!defined($result)) { nagexit('CRITICAL', "Failed to query device $host") };

nagexit($ErrorMap{$result->{$OIDS{miscGlobalStatus.0}}}, $result->{$OIDS{miscGlobalStatusMessage.0}});


sub nagexit($$) {
	my $errlevel = shift;
	my $string = shift;

	print "$errlevel: $string\n";
	exit $ERRORS{$errlevel};
}
