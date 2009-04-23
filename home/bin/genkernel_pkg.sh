#!/bin/sh

GENSRC="/var/gitroot/wolf31o2.org/projs/genkernel"
GK_V="`grep 'GK_V=' ${GENSRC}/genkernel | cut -d= -f2`"
GK_V="${GK_V//\"/}"
GK_V="${GK_V//\'/}"

echo -n "* Building genkernel-${GK_V} tarball"
sleep 1; echo -n '.'; sleep 1; echo -n '.'; sleep 1; echo -n '.';

cp -Rv --remove-destination "${GENSRC}" "genkernel-${GK_V}" || exit 1
cd "genkernel-${GK_V}"
find -name CVS -type d | xargs -t rm -rf
find -name .svn -type d | xargs -t rm -rf
find -name .git -type d | xargs -t rm -rf
chmod +x *.sh
chmod -x README TODO genkernel.conf
rm -f pkg/*.bz2 pkg/*.h pkg/*.patch
cd ..
# exit
 tar -cvjf "genkernel-${GK_V}.tar.bz2" "genkernel-${GK_V}/"
# 
