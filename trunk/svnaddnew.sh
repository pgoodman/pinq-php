#!/bin/sh

svn add \
    `svn status | awk '/^\?/{print $2}'` \
${1+"$@"}
