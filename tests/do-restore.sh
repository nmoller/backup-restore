#!/usr/bin/env bash

# 2015-10-16: Ce serait bien de définir un path fixe au lieu de ../
# pour tester ce sera assez.

for var in $( ls ../*.mbz)
do
  echo $var 
  php restore.php --file $var --cat 1
done 
