#!/bin/bash
set -e

export LANGUAGE="$1"

export CWD="`pwd`"

if [ ! -f "$CWD/meta/bin/phpdocmeta" ]; then
  echo "This seems to be the wrong directory"
  exit 1;
fi

cd "$CWD/$LANGUAGE"

git svn rebase

if [ -f "$CWD/$LANGUAGE/gitlog" ]; then
  rm "$CWD/$LANGUAGE/gitlog"
fi
if [ -f "./last_modified_commit" ]; then
  git filter-branch -f --tree-filter "$CWD/meta/bin/phpdocmeta replaceEnglishRevisionTag -t $CWD/en/hash.table" --commit-filter 'echo -n "${GIT_COMMIT}," >>"${CWD}/${LANGUAGE}/gitlog"; git commit-tree "$@" | tee -a "${CWD}/${LANGUAGE}/gitlog"' `cat last_modified_commit`..HEAD
else
  git filter-branch -f --tree-filter "$CWD/meta/bin/phpdocmeta replaceEnglishRevisionTag -t $CWD/en/hash.table" --commit-filter 'echo -n "${GIT_COMMIT}," >>"${CWD}/${LANGUAGE}/gitlog"; git commit-tree "$@" | tee -a "${CWD}/${LANGUAGE}/gitlog"'
fi
# Use the oldHash=>newHash Map to modify all the relevant metadata-files
# and replace the old hash with the new one
while read line; do IFS="," read -ra HASH <<< $line; ../meta/bin/phpdocmeta replaceHashForRevisionInSvnRefTable -o ${HASH[0]} -r ${HASH[1]}; done < "$CWD/$LANGUAGE/gitlog"
# Remove the hash-map file
rm -rf "$CWD/$LANGUAGE/gitlog"
# Note the last changed commit for the next run
git rev-parse HEAD > last_modified_commit
