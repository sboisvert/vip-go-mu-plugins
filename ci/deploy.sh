#!/bin/bash

# called by Travis CI

set -ex

if [[ "false" != "$TRAVIS_PULL_REQUEST" ]]; then
	echo "Not deploying pull requests."
	exit
fi

if [[ "$TRAVIS_BRANCH" != "$DEPLOY_BRANCH" ]]; then
	echo "Not on the '${DEPLOY_BRANCH}' branch."
	exit
fi

DEPLOY_BUILD_DIR="/tmp/deploy_build/"

# Anyone can read the build log, so it MUST NOT contain any sensitive data,
# turn off script echoing for a bit
set +x

# extract private key from decrypted environment variables stored in .travis.yml
mkdir -p ~/.ssh
echo -n $id_rsa_{00..30} >> ~/.ssh/id_rsa_base64
base64 --decode --ignore-garbage ~/.ssh/id_rsa_base64 > ~/.ssh/id_rsa
chmod 600 ~/.ssh/id_rsa

# Restore script echoing now we've done the private things
set -x

# Previously we set the SSH config to use the wpcomvip-deploy user, this
# user does not have write permissions on our receiving repository
# Remove the SSH config
rm -v ~/.ssh/config

git clone git@github.com:Automattic/vip-mu-plugins-public.git /tmp/target

mkdir -p ${DEPLOY_BUILD_DIR}

# Copy the files into the build dir
cp -pr ${TRAVIS_BUILD_DIR}/* ${DEPLOY_BUILD_DIR}
cp -prv ${TRAVIS_BUILD_DIR}/.[a-zA-Z0-9]* ${DEPLOY_BUILD_DIR}

ls -alh ${TRAVIS_BUILD_DIR}
ls -alh ${DEPLOY_BUILD_DIR}

# Some of the commands below may fail
set +e

# Remove VCS config
find ${DEPLOY_BUILD_DIR} -name ".svn" -exec rm -rfv {} \; 2> /dev/null
find ${DEPLOY_BUILD_DIR} -name ".git*" -exec rm -rfv {} \; 2> /dev/null

# Remove everything unnecessary to running this (tests, deploy scripts, etc)
rm -v ${DEPLOY_BUILD_DIR}/README.md
mv -v ${DEPLOY_BUILD_DIR}/README-PUBLIC.md ${DEPLOY_BUILD_DIR}/README.md
# @FIXME: We will need to replace this ci dir with one which can run tests on the public repo
# BUT this public ci dir cannot include our insecure key (and does not need to)
rm -rfv ${DEPLOY_BUILD_DIR}/ci/
rm -v ${DEPLOY_BUILD_DIR}/.travis.yml

# We've finished with commands which may fail, so return to exiting the script
# if any command exits with a non-zero
set -e

# Copy the Git config into place
mv /tmp/target/.git ${DEPLOY_BUILD_DIR}

cd ${DEPLOY_BUILD_DIR}

git config user.name "Travis CI"
git config user.email "travis@travis-ci.com"
git config push.default "current"

git add -A .
# If you get an SSH prompt to enter a passphrase, you likely encrypted then
# key against the wrong repository
git commit -am "Built from ${TRAVIS_REPO_SLUG}@${TRAVIS_COMMIT}"
git push
