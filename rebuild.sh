#!/bin/bash
sh make.sh
cr=$?
if [ ${cr} -eq 0 ]
then
        sh restart.sh
fi
exit 0
