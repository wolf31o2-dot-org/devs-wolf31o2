#!/bin/bash

# Grabs the latest IPv4 RESERVED/UNALLOCATED list and output it, which is great
# for updating firewall scripts and ACLs.  It (obviously) requires wget.

wget -O - http://www.iana.org/assignments/ipv4-address-space | grep \
	-e RESERVED \
	-e UNALLOCATED
