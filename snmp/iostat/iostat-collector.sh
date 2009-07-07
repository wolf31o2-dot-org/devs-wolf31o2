#!/bin/bash

# Check if iostat is running, if it is, kill it
pids=`pidof iostat`

for pid in $pids
do
	kill $pid
	sleep 0.5
	kill -9 $pid
	sleep 0.5
done

iostat -xkd 30 2 > /tmp/io.tmp && mv /tmp/io.tmp /tmp/iostat.cache

exit 0
