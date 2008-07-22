#!/bin/sh

svn del \
    `svn status | awk '/^\!/{print $2}'` \
${1+"$@"}