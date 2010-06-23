#!/bin/bash

# http://wiki.apache.org/hadoop/GitAndHadoop

# Assume we want to setup in a subdirectory apache.org
# cd /home/chrisgi/git
mkdir -p apache.org
cd apache.org

# core hadoop repositories
for i in common hdfs mapreduce ; do
	git clone git://git.apache.org/hadoop-$i.git
done

# additional top-level repositories
for i in avro hbase hive pig thrift zookeeper ; do
	git clone git://git.apache.org/$i.git
done

# This is only available via SVN... boo!
__svnonly=chukwa 

# git-svn clone --stdlayout http://svn.apache.org/repos/asf/hadoop/$i
# Only track trunk
if which git-svn ; then
	__svn_co_command="git-svn clone"
elif -x /usr/libexec/git-core/git-svn ; then
	__svn_co_command="git svn clone"
elif which svn ; then
	__svn_co_command="svn co"
else
	echo "Cannot find a usable svn client" && exit 1
fi

# Now, grab them
for i in ${__svnonly} ; do
	${__svn_co_command} http://svn.apache.org/repos/asf/hadoop/${i}/trunk ${i}
done

