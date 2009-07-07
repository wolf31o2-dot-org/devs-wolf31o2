#!/bin/bash
echo $* | awk 'BEGIN {ORS=" "} {for (i=1;i<=NF;i++){fc=substr($i,1,1); rest=substr($i,2,length($i)-1);print toupper(fc)rest}} END {ORS=""; print "\n"}'
