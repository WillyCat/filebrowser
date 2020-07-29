#!/bin/bash
./make.sh
cr=$?
if [ ${cr} -eq 0 ]
then
        ./restart.sh
fi
exit 0
