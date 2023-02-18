#!/bin/sh
pwd_dir=$(cd $(dirname $0); pwd)
echo $pwd_dir
cd $(dirname $0)
git fetch --all
git reset --hard origin/master