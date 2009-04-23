#!/bin/sh

CLSTSRC="/data/repos/git/wolf31o2.org/projs/catalyst"
CLST_V="`grep '__version__=' ${CLSTSRC}/catalyst | cut -d= -f2`"
CLST_V="${CLST_V//\"/}"
CLST_V="${CLST_V//\'/}"

echo -n "* Building catalyst-${CLST_V} tarball"
sleep 1; echo -n '.'; sleep 1; echo -n '.'; sleep 1; echo -n '.';

cp -Rv --remove-destination "${CLSTSRC}" "catalyst-${CLST_V}" || exit 1
cd "catalyst-${CLST_V}"
find -name CVS -type d | xargs -t rm -rf
find -name .svn -type d | xargs -t rm -rf
find -name .git -type d | xargs -t rm -rf
chmod -x README AUTHORS ChangeLog* COPYING
cd ..
# exit
 tar -cvjf "catalyst-${CLST_V}.tar.bz2" "catalyst-${CLST_V}/"
# 
