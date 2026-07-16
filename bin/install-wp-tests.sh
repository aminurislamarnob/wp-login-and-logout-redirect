#!/usr/bin/env bash
# Installs the WordPress core test suite + a scratch database for PHPUnit.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#
# Local default (Homebrew MySQL): bin/install-wp-tests.sh wplalr_tests root root 127.0.0.1 latest

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
	# -L: GitHub's archive URLs redirect to codeload, so redirects must be followed.
	if [ "$(which curl)" ]; then
		curl -sL "$1" > "$2";
	elif [ "$(which wget)" ]; then
		wget -nv -O "$2" "$1"
	fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		# version x.x.0 means the first release of the major version, so strip off the .0 and use the branch instead
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	# grab the latest tag from the API
	download http://api.wordpress.org/core/version-check/1.7/ "$TMPDIR"/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' "$TMPDIR"/wp-latest.json | sed 's/"version":"//' | head -1)
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
	WP_VERSION=$LATEST_VERSION
fi

set -ex

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		echo "WordPress already present at $WP_CORE_DIR"
		return;
	fi

	mkdir -p "$WP_CORE_DIR"

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p "$TMPDIR"/wordpress-trunk
		rm -rf "$TMPDIR"/wordpress-trunk/*
		download https://wordpress.org/nightly-builds/wordpress-latest.zip "$TMPDIR"/wordpress-nightly.zip
		unzip -q "$TMPDIR"/wordpress-nightly.zip -d "$TMPDIR"/wordpress-trunk
		mv "$TMPDIR"/wordpress-trunk/wordpress/* "$WP_CORE_DIR"
	else
		download https://wordpress.org/wordpress-"$WP_VERSION".tar.gz "$TMPDIR"/wordpress.tar.gz
		tar --strip-components=1 -zxmf "$TMPDIR"/wordpress.tar.gz -C "$WP_CORE_DIR"
	fi

	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR"/wp-content/db.php
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d "$WP_TESTS_DIR"/includes ]; then
		mkdir -p "$WP_TESTS_DIR"
		rm -rf "$WP_TESTS_DIR"/{includes,data}

		if [ "$(which svn)" ]; then
			svn co --quiet https://develop.svn.wordpress.org/"${WP_TESTS_TAG}"/tests/phpunit/includes/ "$WP_TESTS_DIR"/includes
			svn co --quiet https://develop.svn.wordpress.org/"${WP_TESTS_TAG}"/tests/phpunit/data/ "$WP_TESTS_DIR"/data
		else
			# No svn (the default on macOS since Xcode 11) — pull the same files
			# from the wordpress-develop git mirror instead.
			echo "svn not found; fetching the test suite from the wordpress-develop git mirror"

			local GH_REF="${WP_TESTS_TAG#tags/}"
			GH_REF="${GH_REF#branches/}"
			if [[ $WP_TESTS_TAG == 'trunk' ]]; then
				GH_REF="trunk"
			fi

			rm -rf "$TMPDIR"/wordpress-develop
			mkdir -p "$TMPDIR"/wordpress-develop
			download "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${GH_REF}.tar.gz" "$TMPDIR"/wordpress-develop.tar.gz

			if ! tar -tzf "$TMPDIR"/wordpress-develop.tar.gz > /dev/null 2>&1; then
				# Not a tag (branch/trunk): retry as a branch archive.
				download "https://github.com/WordPress/wordpress-develop/archive/refs/heads/${GH_REF}.tar.gz" "$TMPDIR"/wordpress-develop.tar.gz
			fi

			tar --strip-components=1 -zxmf "$TMPDIR"/wordpress-develop.tar.gz -C "$TMPDIR"/wordpress-develop
			mv "$TMPDIR"/wordpress-develop/tests/phpunit/includes "$WP_TESTS_DIR"/includes
			mv "$TMPDIR"/wordpress-develop/tests/phpunit/data "$WP_TESTS_DIR"/data
		fi
	fi

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/"${WP_TESTS_TAG}"/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		# remove all forward slashes in the end
		WP_CORE_DIR=$(echo "$WP_CORE_DIR" | sed "s:/\+$::")
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s:__DIR__ . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi
}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]; then
		mysqladmin drop "$DB_NAME" -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
		create_db
		echo "Recreated the database ($DB_NAME)."
	else
		echo "Leaving the existing database ($DB_NAME) in place."
	fi
	shopt -u nocasematch
}

create_db() {
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {
	if [ "${SKIP_DB_CREATE}" = "true" ]; then
		return 0
	fi

	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z "$DB_HOSTNAME" ] ; then
		if [ "$(echo "$DB_SOCK_OR_PORT" | grep -e '^[0-9]\{1,\}$')" ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z "$DB_SOCK_OR_PORT" ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z "$DB_HOSTNAME" ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	if [ $(mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute='show databases;' | grep ^"$DB_NAME"$) ]
	then
		echo "Reinstalling will delete the existing test database ($DB_NAME)"
		read -p 'Are you sure you want to proceed? [y/N]: ' DELETE_EXISTING_DB
		recreate_db "$DELETE_EXISTING_DB"
	else
		create_db
	fi
}

install_wp
install_test_suite
install_db
