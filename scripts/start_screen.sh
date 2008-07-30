#!/bin/bash

screen -ls | grep "No Sockets" && screen -d -m

if ! screen -x
then
	screen -wipe
	screen -x
fi
