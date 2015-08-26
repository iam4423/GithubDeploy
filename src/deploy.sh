#! /usr/bin/bash -x

# check for valid args
if [ $# -ne 7 ] ; then
  echo "Invalid arg count"
  exit(0)
fi

# assign vars
git=$1
livePath=$2
deployPath=$3
excludeFiles=$4
deployBranch=$5
htdocsBranch=$6

# pull latest
cd $deployPath
$git fetch origin $deployBranch

# update merger branch
$git checkout $deployBranch
$git reset --hard origin/$deployBranch

# update any excluded files from the live site
if [ -f $excludeFiles ] ; then
  while read line ; do
    cp -f $livePath/$line $deployPath/$line
  done < $exclideFiles
fi

# commit changes
$git add .
$git commit -am 'deploy prep complete'

# update live worktree
cd $livePath
$git reset --hard $deployBranch
