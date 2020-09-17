#!/bin/bash
if [ ! -d large ]
then
	mkdir large
fi

cd large
for l1 in $(seq 1 100000)
do
	fn=$(printf %06d $l1)
	touch ${fn}
done
