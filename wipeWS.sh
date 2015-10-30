#!/bin/bash

ls | while read -r dir
do
  mv "$dir" `echo $dir|tr -d ' '`
done
