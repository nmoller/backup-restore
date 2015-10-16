#!/usr/bin/env bash

if [ -n "$1" ]
then
   echo "fichier avec shortnames"
else
  exit # sortir
fi

while read sn
do
  php backup.php --path /home/nmoller/backup --sn $sn
done < $1
